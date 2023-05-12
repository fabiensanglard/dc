<?php include 'header.php';?>

<h1>The Compiler Driver (1/5)</h1>
<a class="arrow" href="index.php">←</a> <a class="arrow" href="cpp.php">→</a> 
<hr/>

<div style="width:30%;float: right; margin-left: 2ch; margin-bottom: 2ch;">
<table class="lined" style="width: 100%; text-align: center; margin-top: 0;">
  <tr>
<td colspan="3">driver<span class="r">*</span>
        </td><td style="border-top-style: hidden; border-right-style: hidden;"></td>
  </tr>

  <tr>
    <td>cpp</td>
    <td>cc</td>
    <td>ld</td>
    <td>loader</td>
  </tr>
</table>
</div>

<p style="margin-top: 0;">
Let's start by clearing a common misconception. When talking about compilers, the names of <code>clang</code>, <code>gcc</code>, or, on Windows, <code>CL.EXE</code> will come to mind. These are the names of CLIs (Command-Line Interface) used to build executables but invoking them doesn't directly call the compilers. These CLIs are in fact <b>compiler drivers</b>.
</p>

<p> Turning source code into an executable is a multiple step process. It can be viewed as a pipeline where stages communicate via artifact files. 

In the case of <b>K&amp;R</b>, program <code>hello.c</code> requires three stages before the computer can greet you.
</p>

<img src="illu/driver.svg" loading=lazy width="210" height="297" style="width:100%; height: auto;" />

<p>
First the source file <code>hello.c</code> is preprocessed into a translation unit (TU) <code>hello.tu</code>. Then the TU is compiled into an object <code>hello.o</code>. Finally the linker turns the object into an executable. Since we did not give the driver a name for the output (<code>-o</code>), the file is named <code>a.out</code>.
</p>

<p>
Compiler drivers are a convenient way to invoke all the tools auto-magically with a single command. We can run the driver in verbose mode (<code>-v</code>) or in dry-run mode (<code>-###</code>) to see what is happening behind the scene.
</p>

<pre><b>$</b> clang <span class="r">-v</span> hello.c
clang <span class="r">-cc1</span> -o hello.o hello.c
ld -o a.out hello.o
</pre>

<p>
There are three important things to notice in this trace. 
</p>

<p>
First, we see that <code>clang</code> is calling itself with the <code>-cc1</code> flag. Because it is convenient for distribution, the CLI contains both the driver and the compiler. By default the executable behaves like a driver but if invoked with the flag <code>-cc1</code>, it behaves like a compiler. Note that the linker (<code>ld</code>) is its own executable and we will see why later.
</p>

<p>
Second, even though three stages were mentioned earlier, we only see two commands issued (one to the compiler <code>clang -cc1</code> and one to the linker <code>ld</code>). The <b>C</b> <b>P</b>re<b>p</b>rocessor (<code>cpp</code>) used to be a standalone program but it is no longer the case. Instead of loading a <code>.c</code> file to memory, write back a <code>.tu</code> to disk, only to load it to memory again, it is much more I/O efficient to pre-process inputs inside the compiler and compile right away.
</p>


<p>
Lastly, and perhaps most importantly, we see the linker <code>ld</code> invocation. Wouldn't it be more efficient to include the linker in the driver, the same way <code>cpp</code> is embedded? No, because of two reasons.
</p>
<ol>
<li>The compiler ingests one <code>.c</code> and outputs one <code>.o</code>. It has a low memory footprint. The linker on the other side, must use all the <code>.o</code> files at once to generate the executable. Keeping all these <code>.o</code> in memory would stress the system too much on big projects.</li> 
<li>Compilers and linkers are complex machines. Keeping them separate adds an indirection layer so new versions of each stage can be deployed without impacting the other. It is thanks to this architecture that <code>clang</code> got a foot in the door by only providing compilation capabilities while leaving linking to GNU's <code>ld</code>. Likewise, the ELF specific <code>gold</code> linker and more recently LLVM's <code>lld</code> were drop-in replacement of GNU's <code>ld</code>.</li>
</ol>



<h2>Is <code>cc</code> a driver?</h2>
<p>
What is this <code>cc</code> CLI we saw in the introduction, mentioned in <b>K&amp;R</b>? It was the name of the command to invoke the compiler driver back in the '70s. These days, <code>cc</code> is no more. But usage of <code>cc</code> was widespread and since it is a convenient indirection layer, it became <a href="https://pubs.opengroup.org/onlinepubs/7908799/xcu/cc.html">part of POSIX</a> and is still used nowadays.
</p>


 Where does it point to? Let's drill down to find out!
<pre><b>$</b> which cc
/usr/bin/cc
<b>$</b> readlink /usr/bin/cc
/etc/alternatives/cc
<b>$</b> readlink /etc/alternatives/cc
/usr/bin/gcc</pre>
<p>
It's GNU <code>gcc</code>! What about the linker? What is <code>/usr/bin/ld</code>?<br/>
</p>	
<pre><b>$</b> readlink /usr/bin/ld
x86_64-linux-gnu-ld
<b>$</b> which x86_64-linux-gnu-ld
/usr/bin/x86_64-linux-gnu-ld
</pre>
<p>
It is GNU's linker! Alternatively, we could have found out using the <code>-v</code> flag.</p>
<pre>
<b>$</b> cc <span class="r">-v</span>
gcc version 11.3.0 (Ubuntu 11.3.0-1ubuntu1~22.04)
<b>$</b> ld <span class="r">-v</span>
GNU ld (GNU Binutils for Ubuntu) 2.38
</pre>




<h2>GNU Binary Utilities (<code>binutils</code>)</h2>
<p>
Throughout these articles, we will use tools to explore the inputs and outputs in the compiler driver pipeline. The set of CLIs we will rely on is called GNU's Binary Utilities, a.k.a <code>binutils</code>. This collection is the cornerstone of the free software world. It is used by countless platforms, from Linux to BSD, not forgetting Darwin, Playstation 1 Dev Kit, Playstation 3 and 4 OS.
</p>

<pre>// List of binutils tools

Name      |  Description
--------------------------------------------------------------
ld        |  The GNU linker.
as        |  The GNU assembler.
--------------------------------------------------------------
addr2line |  Converts addresses into filenames and line numbers.
ar        |  Creates, modifies and extracts from archives.
c++filt   |  Filter to demangle encoded C++ symbols.
dlltool   |  Creates files for building and using DLLs.
gold      |  New, faster, ELF only linker, 5x faster than ld.
gprof     |  Displays profiling information.
ldd       |  List libraries imported by object file.
nlmconv   |  Converts object code into an NLM.
nm        |  Lists symbols from object files.
objcopy   |  Copies and translates object files.
objdump   |  Displays information from object files.
ranlib    |  Generates an index to the contents of an archive.
readelf   |  Displays information from any ELF format object file.
size      |  Lists the section sizes of an object or archive file.
strings   |  Lists printable strings from files.
strip     |  Discards symbols.
windmc    |  Windows compatible message compiler.
windres   |  Compiler for Windows resource files. </pre>
<p>
Mastering the usage of <code>binutils</code> is a wise investment of a programmer's time. Not only is the knowledge highly reusable across the aforementioned systems, these tools are often the building block of new languages, including recent ones like <code>golang</code> and <code>rust</code>.
</p>

<p>Let's take the example of <code>hello-world</code> in golang.</p>
<pre>package main
import "fmt"

func main() {
    fmt.Println("Hello world")
}</pre>

<p>We can build it and find its entry address with <code>readelf</code> and its entry symbol with <code>nm</code>.</p>
<pre>
<b>$</b> go build hello-world.go
<b>$</b> ./hello-world 
hello world
<b>$</b> <span class="r">readelf</span> -h hello-world | grep Entry
  Entry point address:               0x6a680
<b>$</b> <span class="r">nm</span> hello-world | grep 6a680
000000000006a680 T _rt0_arm64_linux  	
</pre>

<p>We can also investigate at the dynamic libraries dependencies of <code>hello-world</code> and find out that go executable are statically linked.
</p>

<pre><b>$</b> ldd ./hello-world 
    not a dynamic executable
</pre>

<div class="t"> Another good time investment of time is to learn how to bring up <code>man</code> and search it with <code>/</code>. <code>man</code> is a treasure trove of information about executables such as <code>bintools</code>, syscalls and C functions. Manual pages are organized in categories indexed by a number.
<pre><b>$</b> man <span class="r">1</span> nm   // Show <span class="r">CLI</span>     nm   documentation
<b>$</b> man <span class="b">2</span> read // Show <span class="b">syscall</span> read documentation
<b>$</b> man <span class="g">3</span> getc // Show <span class="g">libc</span>    getc documentation</pre>
If you forget which one is which, you can request the manual about the manual.
<pre><b>$</b> man man
   1   Executable programs or shell commands
   2   System calls (functions provided by the kernel)
   3   Library calls (functions within program libraries)
   4   Special files (usually found in /dev)
   5   File formats and conventions, e.g. /etc/passwd
   6   Games
</pre>


</div>




<h2>Driver flags vs Compiler flags vs Linker flags?</h2>
<p>
Since a programmer mostly interacts with the driver, flags and parameters must be routed to the appropriate component. It is a good idea to identify which elements are the intended target.
</p>
<pre><b>$</b> clang <span class="g">-v</span> <span class="r">-lm</span> <span class="b">-std=c89</span> hello.c
clang -cc1 <span class="b">-std=c89</span> -o hello.o hello.c
ld -<span class="r">lm</span> -o a.out hello.o
</pre>
<p>
In the previous trace, notice how <code>-v</code> is consumed by the compiler driver. It has no impact on the compiler or the linker. The driver detected that option <code>-std=c89</code> was intended for the compiler and routed it automatically. Likewise, the driver forwarded <code>-lm</code> to the linker.
</p>
<p>
The driver will detect most commonly used parameters but you can also use a wrapper for a block to be blindly forwarded by the driver to the linker via <code>-Wl</code>.
</p>
<pre><b>$</b> clang -v <span class="r">-Wl,foo,bar</span> hello.c
clang -cc1 -o hello.o hello.c
ld -o a.out hello.o <span class="r">foo bar</span>
</pre>

<div class="t"> We use <code>clang</code> verbose output because they are easier to read. Here is what <code>gcc -v</code> looks like.<br/>
</p>
<pre>
<b>$</b> gcc -v hello.c
COLLECT_GCC_OPTIONS=...
 as -v -EL -mabi=lp64 -o /tmp/ccUkKMFH.o /tmp/ccCKUy4c.s
COMPILER_PATH==...
COLLECT_GCC_OPTIONS=...
 /usr/lib/gcc/aarch64-linux-gnu/11/collect2 /tmp/ccIPliNQ.o
</pre>
<p>
It is a lot harder to read but we can still make out that an assembler step <code>as</code> took place, followed by a linking step via <code>collect2</code>. The call to the compiler via flag <code>-cc1</code> however is not featured.
</p>
</div>

<h2>Driving a multi-file project</h2>
<p>
Let's take a look at what happens when a project is made of multiple C files.
</p>

<pre><b>$</b> clang -v <span class="r">hello</span>.c <span class="b">foo</span>.c <span class="g">bar</span>.c
clang -cc1 <span class="r">hello</span>.c -o <span class="r">hello</span>.o     // Compile
clang -cc1 <span class="b">foo</span>.c -o <span class="b">foo</span>.o         // Compile
clang -cc1 <span class="g">bar</span>.c -o <span class="g">bar</span>.o         // Compile

ld -o a.out hello.o foo.o bar.o    // Link
</pre>

<p>
The driver turned three source files into three object files before linking them together into an executable. The verbose mode shows the compilation steps in sequence but it is important to understand they are in fact completely independent from each other. Looking at a dependency graph explains it better.
</p>

<img src="illu/multi_driver.svg" loading=lazy width="208" height="108" style="width:100%; height: auto;"/>
<p>
The driver ran all the steps sequentially but build systems leverage this translation unit isolation to drastically reduce their wall-time duration. They purposely avoid using the driver to spawn multiple compilers to turn source files into objects in parallel. These build systems also maintain a dependency graph to track which source files an object file depends on to re-compile only what has changed to dramatically speed up incremental compilation.
</p>

<h2>The linker bottleneck</h2>
<p>
The compilation stage scales linearly with the number of source files. The linking stage however does not. As you can see in the previous illustration, the linking stage depends on all object files which means that any file change, whether they are source or header will result in a full linking stage from scratch. Moreover, linking  cannot be parallelized.
</p>
<p>
As a result, linkers are highly optimized. Apple, for example, leveraged multicores to <a href="https://developer.apple.com/videos/play/wwdc2022/110362/">improve the speed</a> of their linker, <code>ld64</code>. Techniques such as Incremental linking, allowing to re-use the result from the previous linking operations, are supported by <code>gold</code>, <code>lld</code>, and Microsoft's linker <code>LINK.EXE</code>.
</p>

<div class="t">
Header files should not be compiled. The purpose of these files is to be included into the translation unit by the Preprocessor (described in the next chapter). If you compile a <code>.h</code>, the output will be a Precompiled Header. These files usually have a <code>.gch</code> extension.

<pre><b>$</b> cat foo.h
int add(int x, int y);
int mul(int x, int y);	
<b>$</b> clang foo.h -o foo.gch
<b>$</b> file foo.gch
GCC precompiled header (version 013) for C 
</pre>
</div>


<h2>clang++ and g++</h2>
<p>Drivers are able to guess many things to make a programmer's life easier but they have their limits. Let's take a look at what happens when we build the C++ version of HelloWorld, <code>hello.cc</code>.
</p>
<pre>
#include &lt;iostream&gt;

int main() {
    std::cout << "Hello World!";
    return 0;
}	
</pre>
<p>
Let's compile it with the same compiler driver command we have used thus far.
</p>
<pre>
<b>$</b> clang -v hello.cc
clang -cc1 -o hello.o foo.cc
ld hello.o -o a.out
<span class="r">hello.cc:(.text+0x11): undefined reference to `std::cout'
.. 265 lines more lines of "Undefined symbols"</span> 
</pre>	

<p>
It doesn't work. The compiler successfully generated an object but the linker failed to find the C++ symbols it needed to generate an executable. C++ source files require special care for which dedicated compiler drivers have been written. GNU GCC has <code>g++</code> and LLVM has <code>clang++</code>.
</p>
<pre>
<b>$</b> clang++ -v hello.cc
clang -cc1 <span class="r">-I/usr/include/c++/10</span> -o hello.o foo.cc
ld hello.o <span class="r">-lc++</span> -o a.out
<b>$</b> ./a.out
Hello World!
</pre>	




<h2>Language detection and name mangling</h2>
<p>
Typically, a driver sets up the header search path for the pre-processor and let the linker know where to find libraries. But there is much more. Let's take the example of a rainbow project using four languages (C, C++, Objective-C, and Objective-C++) and compile it. Since we are only interested in generating the object files, we request it to the driver via flag <code>-c</code>.
</p>

<pre><b>$</b> clang -v <span class="r">-c</span> foo.c bar.cc toto.cpp baz.m qux.mm
clang -cc1 -o hello-f4.o <span class="r">-x c</span> foo.c
clang -cc1 -o hello-ea.o <span class="r">-x c++</span> bar.cc
clang -cc1 -o hello-fa.o <span class="r">-x c++</span> too.cpp
clang -cc1 -o hello-a1.o <span class="r">-x objective-c</span> baz.m
clang -cc1 -o hello-12.o <span class="r">-x objective-c++</span> qux.mm
</pre>

<p>Notice how the driver provides the language of the source file. Isn't it stating the obvious? Not really. Sometimes, a file must be compiled in a different language than its extension indicates. Compiling the same project with a C++ driver, <code>clang++</code>, shows the difference.</p>

<pre><b>$</b><b>$</b> clang++ -v -c foo.c bar.cc toto.cpp baz.m qux.mm
clang -cc1 -o hello-f4.o -x <span class="r">c++</span> foo.c
clang -cc1 -o hello-ea.o -x c++ bar.cc
clang -cc1 -o hello-fa.o -x c++ too.cpp
clang -cc1 -o hello-a1.o -x <span class="r">objective-c++</span> baz.m
clang -cc1 -o hello-12.o -x objective-c++ qux.mm
</pre>

<p>
Notice how the driver requested from the compiler to treat the C source file (<code>foo.c</code>) and Objective-C file (<code>baz.m</code>) to be respectively interpreted as C++ and Objective-C++.
</p>






<h2>clang-cl.exe</h2>
<p>
Let's finish the part about compiler drivers with a clever one called <code>clang-cl.exe</code>.
</p>

<p>
 In Microsoft world, Visual Studio IDE backend uses a compiler driver named <code>CL.EXE</code> which flags are incompatible with those used by LLVM's <code>clang</code>. Sometimes the flags differences are minimal e.g: To enable all warnings, <code>/Wall</code> is needed in <code>CL.EXE</code> while <code>-Wall</code> is used in <code>clang</code>. But most of the time, flags are completely different.

</p>
<p>
 To allow Visual Studio to use <code>clang</code> as its backend, LLVM team created <code>clang-cl.exe</code> driver which converts Microsoft's <code>CL.EXE</code> flags into LLVM ones. In the following example, Visual Studio requested RTTI support (<code>/GR-</code>) and made <code>char</code> unsigned (<code>/J</code>). See how <code>clang-cl.exe</code> converted these flags into something <code>clang -cc1</code> compiler could understand.
<pre>
<b>$</b> clang-cl.exe -v -c -o hello.o <span class="r">/GR-</span> <span class="b">/J</span> hello.c	
clang <span class="r">-frtti</span> <span class="b">-funsigned-char</span> -o hello.o  hello.c
</pre>


<h1>Next</h1>
<hr/>
<p>
<a href="cpp.php">The Preprocessor (2/5)</a>
</p>


<?php include 'footer.php'?>
