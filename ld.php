<?php include 'header.php';?>



<h1 style="margin-bottom: 0.5ch;">The Linker (4/5)</h1>
<a class="arrow" href="cc.php">←</a> <a class="arrow" href="loader.php">→</a> 
<hr/>

<div style="width:30%;float: right; margin-left: 2ch; margin-bottom: 2ch;">
<table class="lined" style="width: 100%; text-align: center; margin-top: 0;">
	<tr>
		<td colspan="3">driver
		</td><td style="border-top-style: hidden; border-right-style: hidden;"></td>
	</tr>

	<tr>
		<td>cpp</td>
		<td>cc</td>
		<td>ld<span class="r">*</span></td>
		<td>loader</td>
	</tr>
</table>
</div>

<p style="margin-top: 0;">

The goal of the linker is to merge all relocatable sections together and create something the OS loader can load for execution. Since we are going to talk about it a lot on this page, let's clarify what relocation means, by quoting <code>elf(5)</code>.</p>

<pre>Relocation is the process of connecting symbolic references with symbolic definitions. 
Relocatable files must have information that describes how to modify their section
contents, thus allowing executable and shared object files to hold the right
information for a processes' program image. Relocation entries are these data.

                                                                           - elf(5)
</pre>

<p>
 The linker starts by picking sections in the relocatable(s) generated by the compiler and merges them together. Along the way, it patches in missing symbols from static libraries and emits relocation information for symbols imported from dynamic libraries.</p>

<img src="illu/ld.svg" width=100% height=300 />

<div class="t" style="margin-top:2ch;"> As the drawing above shows, a static library <code>.a</code> is nothing else but a collection of relocatable <code>.o</code>. It is built using <code>ar</code> (for <b>ar</b>chiver) command.
<pre><b>$</b> clang -c x.c y.c
<b>$</b> <span class="r">ar</span> -rv foolib.a x.o y.o
</pre>
<p>In the good old days you needed to run <code>ranlib</code> on it in order to build an index which speeds up the linking process. Nowadays the default behavior of <code>ar</code> was changed to build this index by default.
</div>

<p>Again, this article is only a high-level overview. If you want to deepen your knowledge of linkers, an excellent book on the topic is <a href="https://amzn.to/3z0ArWr">Linkers and Loaders</a> by John R. Levine.





<h2>Output format</h2>
<p>On Linux the output format is an ELF file (the same as the input). However using <code>readelf</code> we can see that whereas compiler outputs only featured sections, linker outputs also feature segments. Segments are used to point and group sections together.  These two views are called Linking View (sections) and Execution View (segments).</p>

<img style="border:none;" width=100% src="illu/elf.svg"/>


<p>Let's compile <code>hello.c</code> and peek inside <code>a.out</code>. </p>

<pre>// hello.c

#include &lt;stdio.h&gt;

int main() {
   printf("Hello, World!");
   return 0;
}
</pre>

<p>Flag <code>-l</code> in <code>readelf</code> requests to show the segment (a.k.a "program headers") instead of the sections.</p>

<pre>
<b>$</b> clang -v hello.c
clang -cc1 -o <span class="b">/tmp/hello-9c2163.o</span> hello.c
<span class="r">/usr/bin/ld</span> -o a.out  <span class="b">/tmp/hello-9c2163.o</span> /lib/crti.o -L/lib -lc -lgcc 
<b>$</b> file a.out 
a.out: ELF 64-bit LSB pie executable, ARM aarch64, version 1 (SYSV), interpreter /lib/ld-linux-aarch64.so.1
<b>$</b> readelf <span class="r">-l</span> -W a.out

Elf file type is DYN (Position-Independent Executable file)
Entry point 0x8c0
There are 9 <span class="r">program headers</span>, starting at offset 64

Program Headers:
  Type           Offset   VirtAddr           PhysAddr           FileSiz  MemSiz   Flg Align
  PHDR           0x000040 0x0000000000000040 0x0000000000000040 0x0001f8 0x0001f8 R   0x8
  INTERP         0x000238 0x0000000000000238 0x0000000000000238 0x00001b 0x00001b R   0x1
      [Requesting program interpreter: /lib/ld-linux-aarch64.so.1]
  LOAD           0x000000 0x0000000000000000 0x0000000000000000 0x0008ac 0x0008ac R E 0x10000
  LOAD           0x000dc8 0x0000000000010dc8 0x0000000000010dc8 0x000270 0x000278 RW  0x10000
  DYNAMIC        0x000dd8 0x0000000000010dd8 0x0000000000010dd8 0x0001e0 0x0001e0 RW  0x8
  NOTE           0x000254 0x0000000000000254 0x0000000000000254 0x000044 0x000044 R   0x4
  GNU_EH_FRAME   0x0007b0 0x00000000000007b0 0x00000000000007b0 0x00003c 0x00003c R   0x4
  GNU_STACK      0x000000 0x0000000000000000 0x0000000000000000 0x000000 0x000000 RW  0x10
  GNU_RELRO      0x000dc8 0x0000000000010dc8 0x0000000000010dc8 0x000238 0x000238 R   0x1

 <span class="r">Section to Segment mapping:</span>
  Segment Sections...
   00     
   01   .interp 
   02   .interp .gnu.hash .dynsym .dynstr rela.dyn .rela.plt .init .plt .text .fini .rodata .eh_frame 
   03   .init_array .fini_array .dynamic .got .got.plt .data .bss 
   04   .dynamic 
   05   .note.gnu.build-id .note.ABI-tag 
   06   .eh_frame_hdr 
   07     
   08   .init_array .fini_array .dynamic .got 

</pre>	

<p>The program headers instruct where group of sections are in the ELF file (<code>PhysAddr</code>) and where they should be mapped in virtual memory (<code>VirtAddr</code>) by the loader.
</p>




<h2>Linker(s)</h2>
<p>As the verbose trace above shows, <code>clang</code> driver invoked itself to compile the source file and then called <code>/usr/bin/ld</code> to link an executable. 
</p>

<p>There are many linkers available on Linux. The first one available on the platform was GNU's, commonly called <code>ld</code>. Later came <code>gold</code> which was built to improve speed. LLVM also released their own linker called <code>lld</code>.
The path <code>/usr/bin/ld</code> is not enough to tell which one it is. But we can dig a little bit.
</p>


</p>

<pre><b>$</b> ll /usr/bin/ld
lrwxrwxrwx 1 root root 20 Nov  2 13:58 /usr/bin/ld -> aarch64-linux-gnu-ld*
<b>$</b> /usb/bin/ld --version
GNU ld (GNU Binutils for Ubuntu) 2.38
</pre>


<h2>The linker bottleneck</h2>
<p>The linking stage is a bottleneck in the compilation pipeline. Contrary to the compiler which can be run in parallel on each translation unit and whose outputs can be cached between runs, the linker must wait until all object files are ready to start linking.</p>


<img src="illu/multi_driver.svg" loading=lazy width="208" height="108" style="width:100%; height: auto;"/>

<p>As a result, significant optimization have targeted the linker. Efforts such as <cpde>gold</cpde>, Apple's <a href="https://developer.apple.com/videos/play/wwdc2022/110362/">WC2022</a> multi-threaded work, or <a href="https://github.com/rui314/mold">mold</a> which claims a 30x speed increase are among many.</p>


<h2>Incremental linking</h2>

<p>The most important optimization is called "Incremental Linking". It consists in re-using work done during the previous linking operation. Few linkers can do it. GNU's <code>ld</code>, LLVM's <code>lld</code>, and Apple's <code>ld64</code> can't do it.
</p>
<p>
<code>gold</code> can do it, but only if you pass a special linker flag, which typical build systems don't. Microsoft's <code>LD.EXE</code> can also do it when given a special flag <code>/INCREMENTAL</code>.</p>


	<h2>How the linker find resources</h2>
	Alike the Preprocessor, the linker does not ship with a hard-coded list of location and libraries path to lookup. These are supplied, respectively via <code>-L</code> and <code>-l</code>, by the driver.
	</p>
	<pre><b>$</b> clang -v hello.c
clang -cc1 -o /tmp/hello-9a2af8.o  hello.c
ld  -o a.out \  <span class="r">
-L/usr/lib/gcc/aarch64-linux-gnu/11 \
-L/lib/aarch64-linux-gnu \
-L/usr/lib/aarch64-linux-gnu \
-L/usr/lib/llvm-14/lib \
-L/lib \
-L/usr/lib \</span>
\  <span class="b">
-lgcc \
-lgcc_s \ 
-lc </span>\
\  <span class="g">
/usr/lib/gcc/aarch64-linux-gnu/11/crtendS.o 
/lib/aarch64-linux-gnu/crtn.o 
/tmp/hello-9a2af8.o  </span>
	</pre>	

<p>In the trace above, the linker is provided with six folders in red, three dynamic libraries in blue, and must link together the objects passed extra parameters in green.</p>

<div class="t"> The name of a library is prefixed with <code>lib</code> and suffixed with the dynamic library extension (on Linux <code>.so</code>) when looked up on the filesystem. Therefore you won't find a file at <code>/lib/aarch64-linux-gnu/c</code> but you will find <code>/lib/aarch64-linux-gnu/<span class="r">lib</span>c<span class="r">.so</span></code>.</div>

<div class="t"> If you peek inside <code>libc.so</code>, you will find out that it is not an ELF file. It is an ASCII text file.
<pre><b>$</b> file /lib/aarch64-linux-gnu/libc.so
/lib/aarch64-linux-gnu/libc.so: ASCII text
</pre>
<p>This text file is a linker script which points to <code>/lib/aarch64-linux-gnu/libc.so.6</code>.
</div>


<h2>Linking libraries</h2>
<p>There are two types of library linking, named static and dynamic. As we saw earlier, a static library is nothing but a collection of object files packaged in a <code>.a</code> archive. These objects are included in the final binary.</p>

<p>Linking against a dynamic library is different. The linker looks up the dynamic library symbols but does not pull them into the final binary. Instead it emits a special section <code>dynsym</code> which lists the name of symbols to be found at runtime, along with a list of dynamic library names where they may be in section <code>.dynamic</code>. We can see the dynamic library an executable needs with either <code>readelf</code> or <code>ldd</code>

<pre><b>$</b> clang -o hello hello.c
<b>$</b> readelf -d hello| grep NEEDED
 0x0000000000000001 (NEEDED)             Shared library: [libc.so.6]
<b>$</b> ldd hello
	linux-vdso.so.1 (0x0000ffff85df9000)
	libc.so.6 => <span class="b">/lib/aarch64-linux-gnu/libc.so.6</span> (0x0000ffff85bd0000)
	/lib/ld-linux-aarch64.so.1 (0x0000ffff85dc0000)	
</pre>

<p>Notice the output of <code>ldd</code> resolves where the library are on the system. It also includes the interpreter path, we will get to this in the next chapter.
</p>


<p>Using <code>readelf</code>, we can see how the imported symbols are suffixed with the name of the dynamic library. The matching library also feature the same suffix in its exported symbols. If the dynamic library has a version, this is also where it is featured (e.g: <b>GLIBC_2.17</b> here).</p>


<pre><b>$</b> readelf -s hello

Symbol table '<span class="g">.dynsym</span>' contains 10 entries:
   Num:    Value          Size Type    Bind   Vis      Ndx Name
     0: 0000000000000000     0 NOTYPE  LOCAL  DEFAULT  UND 
     1: 00000000000005b8     0 SECTION LOCAL  DEFAULT   11 .init
     2: 0000000000011028     0 SECTION LOCAL  DEFAULT   23 .data
     3: 0000000000000000     0 FUNC    GLOBAL DEFAULT  UND _[...]@GLIBC_2.34 (2)
     4: 0000000000000000     0 NOTYPE  WEAK   DEFAULT  UND _ITM_deregisterT[...]
     5: 0000000000000000     0 FUNC    WEAK   DEFAULT  UND _[...]@GLIBC_2.17 (3)
     6: 0000000000000000     0 NOTYPE  WEAK   DEFAULT  UND __gmon_start__
     7: 0000000000000000     0 FUNC    GLOBAL DEFAULT  UND abort@GLIBC_2.17 (3)
     8: 0000000000000000     0 NOTYPE  WEAK   DEFAULT  UND _ITM_registerTMC[...]
     9: 0000000000000000     0 FUNC    GLOBAL DEFAULT  UND <span class="r">printf</span>@GLIBC_2.17 (3)
 </pre>


<pre><b>$</b> readelf -s <span class="b">/lib/aarch64-linux-gnu/libc.so.6</span> | grep printf

Symbol table '<span class="g">.symtab</span>' contains 90 entries:
   Num:    Value          Size Type    Bind   Vis      Ndx Name
    60: 000000000006cfe0   168 FUNC    GLOBAL DEFAULT   12 swprintf@@GLIBC_2.17
   259: 000000000006d090    56 FUNC    GLOBAL DEFAULT   12 vwprintf@@GLIBC_2.17
   437: 0000000000072184    40 FUNC    WEAK   DEFAULT   12 vasprintf@@GLIBC_2.17
   578: 0000000000050cb0   168 FUNC    GLOBAL DEFAULT   12 dprintf@@GLIBC_2.17
   761: 0000000000050920   168 FUNC    GLOBAL DEFAULT   12 fprintf@@GLIBC_2.17
  1137: 0000000000050d60    40 FUNC    WEAK   DEFAULT   12 vfwprintf@@GLIBC_2.17
  1188: 0000000000050c00   168 FUNC    WEAK   DEFAULT   12 asprintf@@GLIBC_2.17
  1302: 0000000000072530    40 FUNC    WEAK   DEFAULT   12 vsnprintf@@GLIBC_2.17
  1401: 0000000000072350    40 FUNC    WEAK   DEFAULT   12 vdprintf@@GLIBC_2.17
  1561: 000000000004be40    40 FUNC    GLOBAL DEFAULT   12 vfprintf@@GLIBC_2.17
  1911: 0000000000050b40   180 FUNC    GLOBAL DEFAULT   12 sprintf@@GLIBC_2.17
  1930: 000000000006cf30   168 FUNC    WEAK   DEFAULT   12 fwprintf@@GLIBC_2.17
  2123: 0000000000050a90   168 FUNC    WEAK   DEFAULT   12 snprintf@@GLIBC_2.17
  2146: 000000000006d4c0    40 FUNC    WEAK   DEFAULT   12 vswprintf@@GLIBC_2.17
  2229: 000000000004be70    56 FUNC    GLOBAL DEFAULT   12 vprintf@@GLIBC_2.17
  2315: 000000000006d0d0   188 FUNC    GLOBAL DEFAULT   12 wprintf@@GLIBC_2.17
  2837: 000000000006ba70   204 FUNC    WEAK   DEFAULT   12 vsprintf@@GLIBC_2.17
  2841: 00000000000509d0   188 FUNC    GLOBAL DEFAULT   12 <span class="r">printf</span>@@GLIBC_2.17
</pre>

<p>Notice the <code>WEAK</code> binding of some symbols which we discussed earlier.
</p>


<h2>Library order in static linking</h2>
<p>While we are on the topic of linker symbol resolution, you should *really* take a few minutes to read Eli Bendersky's <a href="https://eli.thegreenplace.net/2013/07/09/library-order-in-static-linking">explanation</a> of linking order in static libraries. In fact, his whole website is a gem which partially inspired this series.</p>



<h2>_start</h2>
<p>What happens if the function where the program starts, <code>main</code>, is mistakenly named <code>maib</code>.</p>

<pre>// hello.c

#include &lt;stdio.h&gt;

int mai<span class="r">b</span>() {
  printf("Hello, World!");
  return 0;
}
</pre>

<p>Let's try to compile it.</p>

<pre><code>$</code> clang mainb.c
/usr/bin/ld: /lib/aarch64-linux-gnu/Scrt1.o: in function `_start':
<span class="r">(.text+0x1c): undefined reference to `main'</span>
/usr/bin/ld: (.text+0x20): undefined reference to `main'
clang: error: linker command failed with exit code 1 (use -v to see invocation)
</pre>

<p>The linking fails because a mysterious object <code>Scrt1.o</code> features a function <code>_start</code> which calls <code>main</code>. That's because the execution of a program does not really begin at <code>main</code>. There are many things to set up before a program can run, among other things the stack must be initialized and the program arguments prepared.</p>


<p>In our example the piece of assembly in charge of initialization is called <code>Scrt1.s</code>. Only when everything is ready, the function  <code>__start</code> calls <code>main</code>,


<code>Scrt1.s</code> can also sometimes be found named <code>ctr0</code>. In both cases, the name is derived from <b>C</b> <b>R</b>un<b>T</b>ime.</p>


<p>Likewise, a program execution does not end after <code>main</code> returns. It is easy to verify using <code>atexit</code> function which is executed by the C runtime after main returns.</p>


<pre>// atexit.c

#include &lt;stdio.h&gt;
#include &lt;stdlib.h&gt;

void bye(void) {
  puts(<span class="b">"Goodbye, cruel world...."</span>);
}

int main(void) { 
  atexit(bye);
  puts(<span class="r">"This is the last function call"</span>);
  return 0;
}
</pre>



<p>Let's see the outputs
</p>

<pre><b>$</b> clang atexit.c
<b>$</b> ./a.out
<span class="r">This is the last function call</span>
<span class="b">Goodbye, cruel world....</span></pre>

<p>If you feel like going even deeper on the topic of C runtime, make sure to read 
the <a href="http://www.muppetlabs.com/~breadbox/software/tiny/teensy.html">Tutorial on Creating Teensy ELF Executables</a>.
</p>




<h2>Common error, when mixing static and dynamic libraries</h2>
<p>Let's say we have a project with three source files. One of them hold a "singleton" <code>char</code> variable named <code>c</code>.</p>

<table>
  <tr>
    <td>
<pre> // main.c
	
#include "stdio.h"	



char getChar();



void setChar(char ch);


int main() {
  setChar(<span class="r">'a'</span>);
  putc(getChar())
}
</pre>
    </td>
    <td>
<pre>// static.c 



char c = <span class="r">'b'</span>;

char getChar() {
  return c;
}








</pre>    	
    </td>
    <td>
<pre>// dynamic.c
	


extern char c;





void setChar(char ch) {
  c = ch;
}




</pre>    	
    </td>
  </tr>
</table>

<p>
 We build the project as an object, a static library, and a dynamic library.
</p>

<pre><b>$</b> clang -o static.o -c                        <span class="r">static.c</span>
<b>$</b> ar rcs libmyStatic.a static.o
<b>$</b> clang -o libmyShared.so -shared  -lmyStatic <span class="r">dynamic.c</span>
<b>$</b> clang -o main -lmyShared -lmyStatic         <span class="r">main.c</span>
</pre>

<p>The dependency graph looks as follows.</p>

<img style="width:100%; border:0;" src="illu/singleton_error.svg" />

<p>What is the program going to display when it runs? Will it be <code>a</code>, <code>b</code>, or <code>42</code>?</p>

<pre><b>$</b> ./main.c
<span class="r">b</span>	
</pre>

<p><code>main</code> calls <code>setChar</code> to set the value of <code>c</code> to 'a' and then prints this very variable it just set. The output expected is therefore 'a'. But when we run, we see 'b' being printed.</p>

<p> This happened because the static library was linked twice. There are two copies of the variables <code>c</code> in the final program. One that is read by <code>getChar()</code> and another one which is written by <code>setChar</code>. As much as possible if you are designing a complex project, try to stick to static libraries.</p>

<h3>Common error, the dreaded "duplicate symbol"</h3>

<p>Some error originate at the compiler level but surface at the linker level. This is the case for the beginners' dreaded "duplicate symbol" (a.k.a LNK4002 in the Windows/Visual Studio world). Here is a mini-project to show the problem.</p>

<table>
<tr>
<td>
<pre>// counter.h

#pragma once

<span class="r">int counter = 0;</span>
int incCounter();
</pre>
</td>
<td>
</td>
</tr>
<tr>
<td>
<pre>// counter.c


#include "counter.h"

void incCounter() {
  counter++;  
}

</pre>


</td>
<td>
<pre>// main.c

#include &lt;stdio.h&gt;
#include "counter.h"  

int main() {
  incCounter();
  printf("%d\n", counter);
}
</pre>
</td>
</tr>
</table>

<p>This is a simple program with a main part and a counter part. It fails to compile.
</p>

<pre>$ clang counter.c main.c<span class="r">
1 warning generated.
duplicate symbol '_counter' in:
    /var/folders/sp/tmp/T/counter-c84ff0.o
    /var/folders/sp/tmp/T/cmain-3e41f8.o
ld: 1 duplicate symbol for architecture x86_64</span></pre>

<p>Let's inspect what is going on. First at the translation unit level and then at the symbol level.</p>



<table>
  <tr>
<td>
<pre><b>$</b> clang -E -o counter.tu counter.c
<b>$</b> cat counter.tu

<span class=r>int counter = 0;</span>
int incCounter();

int incCounter() {
  counter++;
}
</pre>

</td>
<td>
<pre><b>$</b> clang -E -o main.tu main.c
<b>$</b> cat main.tu

<span class=r>int counter = 0;</span>
int incCounter();

int main() {
  printf("%d\n", counter);
}
</pre>
</td>
</tr>
</table>

<p>Let's look at the symbols now.</p>
<table>
  <tr>
<td>
<pre><b>$</b> clang -c -o counter.o counter.c
<b>$</b> nm counter.o
0000000000000000 B <span class=r>counter</span>
0000000000000000 T incCounter

</pre>

</td>
<td>
<pre><b>$</b> clang -c -o main.o main.c
<b>$</b> nm main.o
0000000000000000 B <span class=r>counter</span>
0000000000000000 T main
                 U printf
</pre>
</td>
</tr>
</table>



<p>Due to the siloed nature of the translation unit, the compiler will happily produce object files, only for the linker to scream bloody murder when it finds duplicate symbols (like in our example <code>counter</code>) without a way to know which one to use.</p>

  <p> Avoid these kinds of errors by never defining anything in a header. Headers should only contain declarations, and only expose the strict minimum. If you need to share a storage symbol, use <code>extern</code>.
</p>


<h2>Linker trust</h2>
<p>There is a certain level of trust when the linker combines object files. For example there is no verification that imported and exported symbol types match.</p>


<table>
  <tr>
<td>
<pre>// trick.c

#include &lt;stdio.h&gt;

extern <span class=r>short</span> i;

int main() {
  printf("i=%d\n", i);
  return 0;
}

</pre>

</td>
<td>
<pre> // i.c



const <span class=r>char*</span> i = "a string!";






</pre>
</td>
</tr>
</table>

<p>The defined type and the declared type of <code>i</code> did not match but the linker happily combined the object files.</p>

<pre><b>$</b> clang trick.c i.c
<b>$</b> ./a.out
<span class=r>2034</span>
</pre>


<h2>Section pruning</h2>
<p>In the compiler page, the "Section Management" part mentioned how to create one section per symbol. This is usually used in conjunction with linker flags to bring in the final product only what is needed. This is achievable by providing the compiler driver with flags for the linker.</p>

<pre><b>$</b> clang -v -ffunction-sections -fdata-sections <span class=r>-Wl,--gc-sections</span> <span class=b>-Wl,--as-needed</span> main
clang -cc1 -o /tmp/main-476f21.o -x c main.c
ld  <span class=r>--gc-sections</span> <span class=b>--as-needed</span> /tmp/main-476f21.o
</pre>

<p>The executable size reduction will vary depending on the project and translation units structures. </p>
<pre><b>$</b> clang -v -ffunction-sections -fdata-sections -Wl,--gc-sections -Wl,--as-needed main
<b>$</b> ll a.out
-rwxrwxr-x 1 leaf leaf <span class=r>8840</span> Apr  4 22:53 a.out*
<b>$</b> clang  main.c
<b>$</b> ll a.out
-rwxrwxr-x 1 leaf leaf <span class=r>9064</span> Apr  4 22:56 a.out*
</pre>

<div class="t"> Libc implementations may look like they use one source file per function in order to reduce code size (<a href="https://android.googlesource.com/platform/bionic/+/refs/heads/master/libc/bionic/">bionic</a>, <a href="https://github.com/fabiensanglard/glibc">GNU libc</a>). However, this is likely to avoid inlining and allow symbol pre-emption.</div>


<h2>Linker script</h2>
<p>The output of the linker is configured by a linker script. It is a powerful mechanism allowing among other things to tell where each section should go in the output file and where they should be mapped in memory by the loader.</p>

<p>Linkers such as <code>ld</code> have default script (visible with the command <code>ld --verbose</code>) and users don't have to worry about it. Using custom scripts is mandatory for toolchains targeting machines with exotic memory mapping.</p>

<p> Let's take the example of <code>ccps</code>, a toolchain to compile for Capcom CPS-1 (arcade machines of the early 90s). The (partial) memory mapping expected by the hardware is as follows.</p>

<table class="lined">
			<tr>
				<th>Address</th>
				<th>Purpose</th>
			</tr>
			<tr>
				<td>0x000000-0x3FFFFF</td>
				<td>ROM</td>
			</tr>
			<tr>
				<td>0x900000-0x92FFFF</td>
				<td>GFXRAM</td>
			</tr>
			
			<tr>
				<td>0xFF0000-0xFFFFFF</td>
				<td>RAM</td>
			</tr>
			
	    </table>

<p><code>ccps</code> achieves this mapping with the following </code> <a href="https://github.com/fabiensanglard/ccps/blob/master/m68k/cps1.lk">linker script</a>.</p>

		<pre>// cps1 Linker Script

OUTPUT_FORMAT("binary")
OUTPUT_ARCH(m68k)
ENTRY(_start)

MEMORY
{
  <span class=r>rom </span>(rx)    : ORIGIN = 0x000000, LENGTH = 0x200000
  <span class=g>gfx_ram</span>(rw) : ORIGIN = 0x900000, LENGTH = 0x2FFFF
  <span class=b>ram</span>(rw)    : ORIGIN = 0xFF0000, LENGTH = 0xFFFF
}
</pre>
<p>First three memory regions are created, with offset and size. Then sections are mapped to memory regions.

<pre>	
SECTIONS {
  .text : {
    *(.text)
    *(.text.*)
    . = ALIGN(4);
  } > <span class=r>rom</span>

  .rodata : {
    *(.rodata)
    *(.rodata.*)
    . = ALIGN(4);
  } > <span class=r>rom</span>

  .gfx_data : {
  } > <span class=g>gfx_ram</span>

  .bss : {
    __bss_start = .;
    *(.bss)
    *(.bss.*)
    _end = .;
    . = ALIGN(4);
  } > <span class=b>ram</span>

  .data : {
    *(.data)
    *(.data.*)
    . = ALIGN(4);
  } > <span class=b>ram</span>
}		
		</pre>

<div class="t"> Ngdevkit toolchain targets the Neo-Geo arcade machine. It is much more <a href="https://github.com/dciabrin/ngdevkit/blob/d4077868bf9b5708db2af800d293560f7dd45935/runtime/ngdevkit.ld">elaborated</a>.</div>

<h1>Next</h1>
<hr/>
<p>
<a href="loader.php">Loader (5/5)</a>
</p>


<?php include 'footer.php'?>