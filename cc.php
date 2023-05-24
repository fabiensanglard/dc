<?php include 'header.php';?>



	<h1>The Compiler (3/5)</h1>
  <a class="arrow" href="cpp.php">←</a> <a class="arrow" href="ld.php">→</a> 
	<hr/>


  <div style="width:30%;float: right; margin-left: 2ch; margin-bottom: 2ch;">
<table class="lined" style="width: 100%; text-align: center; margin-top: 0;">
  <tr>
   <td colspan="3">driver
    </td><td style="border-top-style: hidden; border-right-style: hidden;"></td>
  </tr>

  <tr>
    <td>cpp</td>
    <td>cc<span class="r">*</span></td>
    <td>ld</td>
    <td>loader</td>
  </tr>
</table>
</div>



<p style="margin-top: 0;">
	The compiler stage is the most complicated element in the pipeline. Because of the purpose of these articles, this is going to be the simplest part. If you want to learn how compilers work inside out, refer to the <a href="https://amzn.to/3JlbOZx">Dragon book</a>.
	</p> 

<h2>Compiler big picture</h2>
<p>The goal of the compiler is to open a translation unit, parse it, optimize it and output an object file (except in the case of LTO which is discussed later). These object files are also sometimes called relocatable.
</p>

<p>All compilers are structured the same way with.</p>

<ul>
 <li>A Frontend which ingest the text and transform it into an Intermediate Representation (IR).</li>

 <li>An Optimizer which iterates on the IR with a collection of optimizations.</li>
 <li>A Backend which generates machine specific instructions. <code>gcc</code> generates assembly and convert it to machine code with <code>binutils</code>'s <code>as</code> while <code>clang</code> has the assembling fully built-in.</li>
	</ul>


<img class="center" style="width:75%; margin-bottom: 2ch; border:0;" src="illu/SimpleCompiler.svg"/>	

<p>The machine code output is packaged into an object file format container.</p>

<div class="t"> Clang is a frontend which generates an IR consumed by LLVM backend. Its well documented and kinda human readable IR format has opened the door to many tools. Among them is Rust's compiler, <code>rustc</code>, which is a LLVM frontend in charge of generating LLVM IR.<br/><br/>
	<img class="center" style="border:0;width:75%;" src="illu/LLVMCompiler1.svg"/>	
</div>


<h2>Output format</h2>
<p>The input format, the translation unit, was studied in the previous section about the preprocessor. Let's focus on what the compiler has to output. The format is given to us via the tool <code>file</code> after requesting the driver to output a relocatable file instead of an executable.</p>

<pre>// mult.c

int mul(int x, int y);

int pow(int x) { return mul(x, x) ; }
</pre>

<p>Note how using <code>-c</code> flag simply made the driver call itself in compiler mode (<code>-cc1</code>) and skip the linker stage.
</p>

<pre><b>$</b> clang -v <span class="r">-c</span> mult.c -o mult.o
clang -cc1 mult.c -o mult.o
<b>$</b> file mult.o
<span class="r">mult.o: ELF 64-bit LSB relocatable, ARM aarch64, version 1 (SYSV), not stripped</span>
</pre>

<p>The relocatable files are commonly called "object" file and use a <code>.o</code> extension. Let's use <code>binutils</code>'s <code>readelf</code> to peek inside it.
</p>

<pre><b>$</b> readelf <span class="r">-S</span> -W mult.o
There are 9 section headers, starting at offset 0x1d8:

Section Headers:
  [Nr] Name              Type            Address          Off    Size   ES Flg Lk Inf Al
  [ 0]                   NULL            0000000000000000 000000 000000 00      0   0  0
  [ 1] .strtab           STRTAB          0000000000000000 0001b1 000071 00      0   0  1
  [ 2] .text             PROGBITS        0000000000000000 000040 000028 00  AX  0   0  4
  [ 3] .rela.text        RELA            0000000000000000 000180 000018 18   I  9   2  8
  [ 4] .comment          PROGBITS        0000000000000000 000068 000026 01  MS  0   0  1
  [ 5] .note.GNU-stack   PROGBITS        0000000000000000 00008e 000000 00      0   0  1
  [ 6] .eh_frame         PROGBITS        0000000000000000 000090 000030 00   A  0   0  8
  [ 7] .rela.eh_frame    RELA            0000000000000000 000198 000018 18   I  9   6  8
  [ 8] .llvm_addrsig     LOOS+0xfff4c03  0000000000000000 0001b0 000001 00   E  9   0  1
  [ 9] .symtab           SYMTAB          0000000000000000 0000c0 0000c0 18      1   6  8
</pre>

<p>The output is organized in named sections. The most important one to know is <code>.text</code>, where the functions instructions are stored. We can experiment with the source code to see the two other most common sections.
</p>


<pre>// manySymbols.c

int myInitializedVar = 1;
int myUnitializedVar;

int add(int x, int y);

int mult(int x) { return add(x, x) ; }
</pre>

<p>Let's compile to a relocatable object and peek inside again.</p>

<pre><b>$</b> clang -c -o manySymbols.o manySymbols.c
<b>$</b> readelf -S -W manySymbols.o
There are 12 section headers, starting at offset 0x2c0:

Section Headers:
  [Nr] Name              Type            Address          Off    Size   ES Flg Lk Inf Al
  [ 0]                   NULL            0000000000000000 000000 000000 00      0   0  0
  [ 1] .strtab           STRTAB          0000000000000000 000219 0000a5 00      0   0  1
  [ 2] .text             PROGBITS        0000000000000000 000040 000028 00  AX  0   0  4
  [ 3] .rela.text        RELA            0000000000000000 0001e8 000018 18   I 11   2  8
  [ 4] <span class="r">.data</span>             PROGBITS        0000000000000000 000068 000004 00  WA  0   0  4
  [ 5] <span class="r">.bss</span>              NOBITS          0000000000000000 00006c 000004 00  WA  0   0  4
  [ 6] .comment          PROGBITS        0000000000000000 00006c 000026 01  MS  0   0  1
  [ 7] .note.GNU-stack   PROGBITS        0000000000000000 000092 000000 00      0   0  1
  [ 8] .eh_frame         PROGBITS        0000000000000000 000098 000030 00   A  0   0  8
  [ 9] .rela.eh_frame    RELA            0000000000000000 000200 000018 18   I 11   8  8
  [10] .llvm_addrsig     LOOS+0xfff4c03  0000000000000000 000218 000001 00   E 11   0  1
  [11] .symtab           SYMTAB          0000000000000000 0000c8 000120 18      1   8  8</pre>


<p>The addition of an initialized variable made the compiler use a <code>.data</code> section. The addition of an uninitialized variable made the compiler use a <code>.bss</code> section.</p>








<h2>Symbols</h2>
<p>A relocatable lists both export symbols and import symbols. These lists are in the <code>.symtab</code> sections, which refers to strings in the <code>.strtab</code> section.
</p> 


<pre><b>$</b> // importExport.c

extern const int myConstant;
extern void foo(int x);

int myVar1;
int myVar2;
void bar() { 
	foo(myConstant); 
}
</pre>

<p>Let's look at the exported and imported symbols with <code>nm</code>.</p>

<pre><b>$</b> clang -c  mult.c -o mult.o
<b>$</b> <span class="r">nm</span> mult.o
0000000000000000 T bar
                 U foo
                 U myConstant
0000000000000000 B myVar1
0000000000000004 B myVar2
</pre>

<p>As expected we find three symbols exported, a function <code>bar</code> (with an offset in <code>.text</code> of <code>0x0</code>) and two uninitialized variables in the <code>bss</code> section. Variable <code>myVar1</code> is at offset <code>0x0</code> and <code>myVar2</code> is four bytes further at offset <code>0x4</code>.
</p>

<p>

 We also see two undefined (a.k.a imported) symbols, <code>foo</code> and <code>myConstant</code> with the <code>U</code> type. These obviously don't have an offset. The complete list of <code>nm</code> letter codes and their meaning is as follows.
	</p>

	<pre>A  A global, absolute symbol.
B  A global "bss" (uninitialized data) symbol.
C  A "common" symbol, representing uninitialized data.
D  A global symbol naming initialized data.
N  A debugger symbol.
R  A read-only data symbol.
T  A global text symbol.
U  An undefined symbol.
V  A weak object.
W  A weak reference.
a  A local absolute symbol.
b  A local "bss" (uninitialized data) symbol.
d  A local data symbol.
r  A local read-only data symbol.
t  A local text symbol.
v  A weak object that is undefined.
w  A weak symbol that is undefined.
?  None of the above.</pre>

<p>We can write a rainbow source file which hits as many types of symbols as possible when compiled to object.</p>

<pre>extern int undVar;                 // Should be U  
int defVar;                        // Should be B

extern const int undConst;         // Should be U
const int defConst = 1;            // Should be R

extern int undInitVar;             // Should be U
int defInitVar = 1;                // Should be D

static int staticVar;              // Should be b
static int staticInitVar=1;        // Should be d
static const int staticConstVar=1; // Should be r

static void staticFun(int x) {}    // Should be t

extern void foo(int x);            // Should be U

void bar(int x) {                  // Should be T 
  foo(undVar);
  staticFun(undConst);
}</pre>

<p>Since we are using an OS with two great compilers available, we can compile with both <code>gcc</code> and <code>clang</code> to see the differences.</p>

<pre><b>$</b> <span class="r">clang</span> -c  rainbow.c -o rainbow.o && nm rainbow.o
0000000000000000 T bar
0000000000000000 R defConst
0000000000000000 D defInitVar
0000000000000000 B defVar
                 U foo
000000000000003c t staticFun
                 U undConst
                 U undVar</pre>

<pre><b>$</b> <span class="r">gcc</span> -c  rainbow.c -o rainbow.o && nm rainbow.o
0000000000000014 T bar
0000000000000000 R defConst
0000000000000000 D defInitVar
0000000000000000 B defVar
                 U foo
0000000000000004 r staticConstVar
0000000000000000 t staticFun
0000000000000004 d staticInitVar
0000000000000004 b staticVar
                 U undConst
                 U undVar
</pre>

<h2>Global symbol / Local symbol</h2>
<p><code>nm</code> outputs differentiate between local and global symbols. A local symbol is only visible within a relocatable unit. In C, this is achieved with a <code>static</code> storage class specifier.
</p>

<p>Global are visible to all relocatable units. It is something that is revisited in the linker article.</p> 

<h2>Weak and strong symbols</h2> 
<p><code>nm</code> output also differentiates between "strong" symbols (the default) and weak symbols.</p>
<p>A weak symbol can be overwritten by a strong symbol. 
	</p>

<table>
  <tr>
    <td>
<pre> // weak.c

#include "stdio.h"

extern int getNumber();

int main() {
  printf("%d\n", <span class="r">getNumber()</span>);
}     
</pre> 
    </td>
    <td>
<pre>// number1.c





int <span class="r">getNumber</span>() {
  return 1;
}  
</pre>      
    </td>
    <td>
<pre>// number2.c





int <span class="r">getNumber</span>() {
  return 2;
}       
</pre>      
    </td>    
  </tr>
</table>

<p>By default all symbols are strong. In this example, the linker fails because it does not know which <code>getNumber</code> to pick. when it is used in <code>weak.c</code>.</p>

<pre><b>$</b> clang -o weak weak.c number1.c number2.c
  /usr/bin/ld: number2.o: in function `getNumber':
number2.c:(.text+0x0): <span class="r">multiple definition of `getNumber')</span>; number1.o:number1.c:(.text+0x0): first defined here
clang: error: linker command failed with exit code 1 (use -v to see invocation
</pre>


<p>If we declare one of the duplicate functions as <code>weak</code>, the program compiles and run normally, regardless of the compilation and linking order.</p>

<table>
  <tr>
    <td>
<pre> // weak.c

#include "stdio.h"

extern int getNumber();

int main() {
  printf("%d\n", getNumber());
}     
</pre> 
    </td>
    <td>
<pre>// number1.c





<span class="r">__attribute__((weak))</span> int getNumber() {
  return 1;
}  
</pre>      
    </td>
    <td>
<pre>// number2.c





int getNumber() {
  return <span class="g">2</span>;
}       
</pre>      
    </td>    
  </tr>
</table>

<pre><b>$</b> clang -o weak weak.c number1.c number2.c
<b>$</b>./weak
<span class="g">2</span>
<b>$</b> clang -o weak weak.c number2.c number1.c
<b>$</b>./weak
<span class="g">2</span>
</pre>


<h2>Weak symbols and libc</h2> 
<p>Most <code>libc</code> implementations declare their methods "weak" so users can intercept them. This is not always as convenient as it seems. Let's look at how to intercept <code>malloc</code>.</p>
<pre>// mymalloc.c

#define _GNU_SOURCE // Could have been defined with -D on command-line

#include "stddef.h"
#include "dlfcn.h"
#include "stdio.h"
#include "stdlib.h"


void* malloc(size_t sz) {
  void *(*libc_malloc)(size_t) = dlsym(RTLD_NEXT, "malloc");
  printf("malloced %zu bytes\n", sz);
  return libc_malloc(sz);
}

int main() {
  char* x = malloc(100);
  return 0;
}
</pre>

<p>This program will enter an infinite loop until it segfaults. This is because <code>dlsym</code> calls <code>malloc</code>.

<pre><b>$</b> clang mymalloc.c
<b>$</b> ./a.out
<span class="r">Segmentation fault (core dumped)</span>
</pre>

<p>For such cases, GNU's <code>libc</code> used to provide special hooks such as <code>__malloc_hook</code>...but they became deprecated. Now the best way is to MITM via the loader and <code>LD_PRELOAD</code>.</p>

<pre> // mtrace.c

#include &lt;stdio.h&gt;
#include &lt;dlfcn.h&gt;

static void* (*real_malloc)(size_t) = nullptr;

void *malloc(size_t size) {
    if(!real_malloc)  {
      real_malloc = dlsym(RTLD_NEXT, "malloc");
    }

    printf("malloc(%d) = ", size);
    return real_malloc(size);
}

</pre>

<pre><b>$</b> clang -shared -fPIC -D_GNU_SOURCE -o mtrace.so mtrace.c
$ LD_PRELOAD=./mtrace.so ls
malloc(472) = 0xaaab24e4b2a0
malloc(120) = 0xaaab24e4b480
malloc(1024) = 0xaaab24e4b500
malloc(5) = 0xaaab24e4b910
...
<b>$</b></pre>

<div class="t"> Weak symbols are also paramount for C++ and especially the STL (see below).</div>












<h2>How C++ template leverage weak symbols</h2>
  <p>There is one further usage of weak symbols. When using STL templates, each relocatable receives a copy of instructions and symbols when instantiation is involved. As a result, two translation units using <code>vector&lt;int&gt;</code> end up with the same symbols.
  </p>
  <table>
    <tr>
      <td>
  <pre>// c++foo.cc

#include &lt;vector&gt;

void foo() {
  auto v = std::vector&lt;int&gt;();
}
  </pre>
     </td>
      <td>
  <pre>// c++bar.cc

#include &lt;vector&gt;

void bar() {
  auto v = std::vector&lt;int&gt;();
}

</pre>
     </td>
   </tr>
  </table>
  <p>
<code>nm</code> confirms the duplicates in both object files.
  </p>
  <pre><b>$</b> clang -c -o c++foo.o c++foo.cc
<b>$</b> nm c++foo.o | grep -E 'vector|bar|foo'
0000000000000000 T foo()
0000000000000000 <span class="r">W</span> std::vector&lt;int, std::allocator&lt;int&gt; &gt;::vector()
0000000000000000 <span class="r">W</span> std::vector&lt;int, std::allocator&lt;int&gt; &gt;::~vector()</pre>
  <pre><b>$</b> clang -c -o c++bar.o c++bar.cc
<b>$</b> nm c++bar.o | grep -E 'vector|bar|foo'
0000000000000000 T bar()
0000000000000000 <span class="r">W</span> std::vector&lt;int, std::allocator&lt;int&gt; &gt;::vector()
0000000000000000 <span class="r">W</span> std::vector&lt;int, std::allocator&lt;int&gt; &gt;::~vector()
  </pre>

  <p>When the linker sees several symbols it favors the "strong" one. However if only "weak" ones are available it picks up any of them without throwing an error. This behavior can be exposed in an example using template and <code>-D</code>.</p>





<table>
    <tr>
      <td>
  <pre>// weak_main.cc

const char* foo();
const char* bar();
#include "stdio.h"

int main() {
  printf("%s\n", foo());
  printf("%s\n", bar());
}

  </pre>
     </td>
        <td>
  <pre>// <span class="b">c++foo.cc</span>


#define NAME "foo"
#include "template.h"

const char* foo() {
  Name<const char*> name;
  return name.get();
}


</pre>
     </td>

      <td>
  <pre>// <span class="g">c++bar.cc</span>


#define NAME "bar"
#include "template.h"

const char* bar() {
  Name<const char*> name;
  return name.get();
}


</pre>
     </td>
     <td>
<pre> // template.h


template&lt;typename T&gt; struct Name {
  T get() const {
    return T{NAME};
  }
};


</typename>     
     </td>
   </tr>
  </table>




  <p>At first sight, the program above should print to the console <code>"foo"</code> and then <code>"bar"</code> but it doesn't. Because of C++ One Definition Rule (ODR) all these symbols are marked as weak so a single one is picked, depending on the order the linker sees them.</p>

  <pre><b>$</b> clang++ -o main weak_main.cc <span class="b">c++foo.cc</span> <span class="g">c++bar.cc</span>
<b>$</b> ./main 
<span class="r">foo
foo</span>
<b>$</b> clang++ -o main weak_main.cc <span class="g">c++bar.cc</span> <span class="b">c++foo.cc</span>
<b>$</b> ./main 
<span class="r">bar
bar</span>
</pre>

<p>The original illustration of this process was found <a href="https://stackoverflow.com/questions/44335046/how-does-the-linker-handle-identical-template-instantiations-across-translation"> here</a>.
  </p>


<h2>Relocation</h2>
<p>The symbols list shows imports and exports names. That is enough for the linker to understand what an object provides and needs but that is not enough to relocate the relocatables. The linker needs the exact location of each symbols in an object. These are stored in relocation tables which <code>readelf</code> can show us.</p>

<pre><b>$</b> readelf <span class="r">-r</span> mult.o

Relocation section '.rela.text' at offset 0x1d8 contains 5 entries:
  Offset          Info           Type           Sym. Value    Sym. Name + Addend
000000000010  000800000137 R_AARCH64_ADR_GOT 0000000000000000 myConstant + 0
000000000014  000800000138 R_AARCH64_LD64_GO 0000000000000000 myConstant + 0
00000000001c  000900000113 R_AARCH64_ADR_PRE 0000000000000000 myVariable + 0
000000000020  00090000011d R_AARCH64_LDST32_ 0000000000000000 myVariable + 0
000000000024  000a0000011b R_AARCH64_CALL26  0000000000000000 add + 0

Relocation section '.rela.eh_frame' at offset 0x250 contains 1 entry:
  Offset          Info           Type           Sym. Value    Sym. Name + Addend
00000000001c  000200000105 R_AARCH64_PREL32  0000000000000000 .text + 0
</pre>

<p>Every single usage of an imported variable/function is present in the relocation table. It provides everything the linker needs like the section to patch, the offset, the type of usage, and of course the symbol name.</p>


<h2>Mangling</h2>

<p>So far we used examples using the C language which results in simple symbol names where function/variable results in a symbol of the same name. Things get more complicated when a language allows function overloading.</p>

<p>
To illustrate mangling, instead of letting the driver detect the language, we can declare it ourselves and see what happens with the symbols table.</p>
<pre>// sample.c

void foo() {};
</pre>	

<p>Let's first compile <code>sample.c</code> as a C file (with <code>-x c</code>) and then as a C++ file <code>-x c++</code>.</p>


<pre><b>$</b> clang -c <span class="r">-x c</span> sample.c -o sample.o
<b>$</b> nm sample.o
0000000000000000 T <span class="r">foo</span>
<b>$</b> clang -c <span class="r">-x c++</span> sample.c -o sample.o
<b>$</b> nm sample.o
0000000000000000 T <span class="r">_Z3foov</span>
</pre>

<p>Thanks to mangling, C++ allows functions to have the same name. They get different symbol names thanks to the parameter types. Symbols avoid function name collision via a special encoding but name mangling can lead to linking issues.
</p>
<table style="width:100%;">
	<tr>
		<td>
		</td>
		<td>
			<pre>// bar.h

void bar();
</pre>
    </td>
  </tr>

	<tr>
		<td>
      <pre>// main.cpp
#include "bar.h"

int main() {
  bar();
  return 0;
}
</pre>
    </td>
    <td>
      <pre>// bar.c 



void bar() {};

      </pre>
    </td>
  </tr>
</table>

<pre><b>$</b> clang main.cpp bar.c -o main <span class="r">
/usr/bin/ld: /tmp/m-7f361c.o: in function `main':
main.cc:(.text+0x18): undefined reference to `bar()'
clang: error: linker command failed with exit code 1 (use -v to see invocation)</span>
</pre>

<p>The project won't link properly because the symbols for the function <code>bar</code> do not match (<code>main.cpp</code> was mangled as C++ but <code>bar.c</code> was mangled as C).</p>

<pre><b>$</b>  nm main.o
0000000000000000 T main
                 U <span class="r">_Z3barv</span>
<b>$</b> nm bar.o
0000000000000000 T <span class="r">bar</span>
</pre>
<p>There is a simple solution. Just use the name mangling C++ expect to name your functions and variables in your C++.</p>

<table style="width:100%;">
	<tr>
		<td>
		</td>
		<td>
			<pre>// bar.h

void <span class="r">_Z3barv</span>();
</pre>
    </td>
  </tr>

	<tr>
		<td>
      <pre>// main.cpp
#include "bar.h"

int main() {
  bar();
  return 0;
}
</pre>
    </td>
    <td>
      <pre>// bar.c 



void <span class="r">_Z3barv</span>() {};

      </pre>
    </td>
  </tr>
</table>

<p>It works, problem solved!</p>
<pre><b>$</b> clang main.cpp bar.c -o main
<b>$</b></pre>

<p>A more serious and realistic solution is to use a macro to let the compiler know that it should generate import symbol names without mangling them. This is done via <code>extern "C"</code>.</p>

<table style="width:100%;">
	<tr>
		<td>
		</td>
		<td>
			<pre>// bar.h
<span class="r">extern "C" {</span>
void bar();
<span class="r">}</span>
</pre>
    </td>
  </tr>

	<tr>
		<td>
      <pre>// main.cpp
#include "bar.h"

int main() {
  bar();
  return 0;
}
</pre>
    </td>
    <td>
      <pre>// bar.c 



void bar() {};

      </pre>
    </td>
  </tr>
</table>

<p>Compilation works, the export/import symbol tables have no mismatch.</p>

<pre><b>$</b> clang main.cpp bar.c -o main 
<b>$</b> nm main.o
0000000000000000 T main
                 U <span class="r">bar</span>
<b>$</b> nm bar.o
0000000000000000 T <span class="r">bar</span>
</pre>






<h2>Section management</h2>
<p>We have seen earlier how variables, constants, and functions end up in three sections <code>text</code>, <code>data</code>, and <code>bss</code> but the compiler can operate at a lower granularity. 
</p>

<p>Instead of generating huge sections, the compiler can generate one section per symbol. This later allows the linker to pick only what is useful and reduce the size of the executable.</p>

<pre>// sections.c

int a = 0;
int b = 0;
int funcA() { return a;}
int funcB() { return b;}
</pre>	
	
<table>
	  
    	
    <tr>
	   <td><pre><b>$</b> clang -c -o sections.o sections.c

<b>$</b> readelf -S -W  sections.o
There are <span class="r">11</span> section headers:

Section Headers:
  [Nr] Name              Type            
  [ 0]                   NULL            
  [ 1] .strtab           STRTAB          
  [ 2] .text             PROGBITS        
  [ 3] .rela.text        RELA            
  [ 4] .bss              NOBITS          
  [ 5] .comment          PROGBITS        
  [ 6] .note.GNU-stack   PROGBITS        
  [ 7] .eh_frame         PROGBITS        
  [ 8] .rela.eh_frame    RELA            
  [ 9] .llvm_addrsig     LOOS+0xfff4c03  
  [10] .symtab           SYMTAB         




    	</pre>
    	</td>

    	<td><pre><b>$</b> clang -c -o sections.o sections.c \
  <span class="r">-ffunction-sections -fdata-sections</span>
<b>$</b> readelf -S -W  sections.o
There are <span class="r">15</span> section headers:

Section Headers:
  [Nr] Name              Type            
  [ 0]                   NULL            
  [ 1] .strtab           STRTAB          
  [ 2] .text             PROGBITS        
  [ 3] <span class="r">.text.funcA</span>       PROGBITS        
  [ 4] <span class="r">.rela.text.funcA</span>  RELA            
  [ 5] <span class="r">.text.funcB</span>       PROGBITS        
  [ 6] <span class="r">.rela.text.funcB</span>  RELA            
  [ 7] .bss.a            NOBITS          
  [ 8] .bss.b            NOBITS          
  [ 9] .comment          PROGBITS        
  [10] .note.GNU-stack   PROGBITS        
  [11] .eh_frame         PROGBITS        
  [12] .rela.eh_frame    RELA            
  [13] .llvm_addrsig     LOOS+0xfff4c03  
  [14] .symtab           SYMTAB       
    </pre>
    	</td>
    </tr>


 </table>

	<h2
	<h2>Optimization level</h2>
	<p>By far the most important flag to pass the compiler is the level of optimization to apply to the IR before generating the instructions. By default, no optimizations are performed. It shows, even with a program doing almost nothing.
	</p>

	<pre>// do_nothing.c

void do_nothing() {
}

int main() {
  for(int i= 0 ; i < 1000000000 i++) 
    do_nothing();
  return 0;  
}
	</pre>
	<p>Let's build and measure how long it takes to do nothing.
	</p>
	<pre><b>$</b> clang do_nothing.c 
<b>$</b> time ./a.out 

<span class="r">real	0m2.374s</span>
user	0m2.104s
sys  	0m0.015s
	</pre>
	<p>This program should have completed near instantly but because of the function call overhead, it took two seconds.  Let's try again but this time, allowing optimization to occur.
	</p>
	<pre><b>$</b> clang do_nothing.c <span class="r">-O3</span>
<b>$</b> time ./a.out 

<span class="r">real	0m0.224s</span>
user	0m0.011s
sys	0m0.014s
	</pre>

<p>While some optimization focuses on runtime, others focus on code size. They are listed <a href="https://gcc.gnu.org/onlinedocs/gcc/Optimize-Options.html">here</a>.
</p>

<div class="t">If you have a few hours to spare, treat yourself <a href="https://binary.ninja/">Binary Ninja</a> and take a look at the marvels optimizers come up with.
</div>










	<h2>The translation unit barrier</h2>
	<p>
	Let's keep iterating with the previous program that does nothing. Compiler optimization <code>-O3</code> is awesome but it has its limitations because it only operates at the translation unit level. Let's see what happens when the <code>do_nothing</code> function is in a different source file.
	</p>

	<table style="width:100%;table-layout:fixed">
	  <tr>
	  	<td>
  		<pre>// opt_main.c

extern void do_nothing();

int main() {
  for(int i= 0 ; i <1000000000 ;i++)
    do_nothing();
}

</pre>
	    </td>
	    <td  style="width:45%;">
	    	<pre>// do_nothing_tu.c

void do_nothing() {
}




	</pre>
	     </td>
	   </tr>
	 </table>

	<pre><b>$</b> clang <span class="r">-O3</span> opt_main.c do_nothing_tu.c </span>
<b>$</b> time ./a.out

<span class="r">real	0m2.056s</span>
user	0m1.824s
sys	0m0.018s
	</pre>
	<p>Even with optimization enabled, we are back to the poor performance of an un-optimized executable. Due to the siloed nature of translation unit processing, the compiler could not decide whether calls to <code>do&#95;nothing</code> should be pruned and generated a callsite anyway.
	</p> 
	<h3>Breaking the barrier the old-school way</h3>
	<p>
	  The solution to this problem would be to perform optimization not at the translation unit level but at the program level. Since only the linker has a vision of all components (and it can only see sections and symbols), this is seemingly not possible.
	</p>
	<p>
	 The trick to make it work is called "artisanal LTO".  It consists in creating a super translation unit, containing all the source code of the program. We can do that with the pre-processor.
	</p>




	<table style="width:100%;">
	  <tr>
	  	<td>
	  			<pre>// all.c

#include "do_nothing.c"
#include "opt_main.c"




</pre>
</td>
	  	<td>
  		<pre>// opt_main.c

extern void do_nothing();

int main() {
  for(int i= 0 ; i <1000000000 ;i++)
    do_nothing();
}
</pre>
     </td>
	    <td  style="width:45%;">
	    	<pre>// do_nothing.c

void do_nothing() {
}




</pre>
	     </td>
	   </tr>
	 </table>

	<p>
Now able to see that <code>do_nothing</code> is a no-op, the compiler is able to optimize it away.
	</p>
	<pre>$ clang all.c -O3</span>
$ time ./a.out
<span class="r">real  0m0.163s</span>
user  0m0.012s
sys 0m0.014s
	</pre>  
<p>Of course the bigger and complex the program, the less practical it is which led to LTO.</p>



	<h2>LTO</h2>
	<p>
Thankfully, the "artisanal LTO" trick is no longer needed. Compilers can outputs extra information in the relocatables for the linker to use. Both <code>GNU</code>'s <code>GCC</code> and <code>LLVM</code> implement Link-Time Optimizations via <code>-flto</code> flag but they do it differently.
	</p>

	<h3>GCC's LTO</h3>
	<p>
	GCC compiler implements LTO in a way that lets the linker fail gracefully if it does not support it. The program will still be linked but without link-time optimizations.
	To this effect, GCC generates fat-objects which not only contains everything an <code>.obj</code> should have but also GCC's intermediate representation (<code>GIMPLE</code> bytecode).
	</p>

	<table style="width:100%;table-layout:fixed">
	  <tr>
	  	<td>
  		<pre>// opt_main.c

extern void do_nothing();

int main() {
  for(int i= 0 ; i <1000000000 ;i++)
    do_nothing();
}
</pre>
	    </td>
	    <td  style="width:45%;">
	    	<pre>// do_nothing.c

void do_nothing() {
}



	</pre>
	     </td>
	   </tr>
	 </table>




	<pre>
<b>$</b> gcc -c main.c -o main.o
<b>$</b> file main.o
main.o: ELF 64-bit LSB relocatable, ARM aarch64, version 1 (SYSV), not stripped
<b>$</b> gcc  -c main.c -o main.o <span class="r">-flto</span>
<b>$</b> file main.o
<span class="r">main.o: ELF 64-bit LSB relocatable, ARM aarch64, version 1 (SYSV), not stripped</span></pre>

<pre>
<b>$</b> gcc main.c do_nothing.c <span class="r">-flto</span>
<b>$</b> time ./a.out

real	0m2.112s
user	0m2.107s
sys	0m0.004s
<b>$</b> gcc <span class="b">-O3</span> <span class="r">-flto</span> -c hello.c -o hello.o
<b>$</b> time ./a.out

real	<span class="b">0m0.002s</span>
user	0m0.000s
sys	0m0.002s</pre>

	<h3>LLVM's LTO</h3>
	LLVM's way to implement LTO is a bit more aggressive. Instead of producing a fat object file, it simply pushes the IR and disguises it as an object by using a <code>.o</code> extension. This is inconvenient because if the linker does not know how to handle bitcode, the compilation process will fail.


	<table style="width:100%;table-layout:fixed">
	  <tr>
	  	<td>
  		<pre>// opt_main.c

extern void do_nothing();

int main() {
  for(int i= 0 ; i <1000000000 ;i++)
    do_nothing();
}
</pre>
	    </td>
	    <td  style="width:45%;">
	    	<pre>// do_nothing.c

void do_nothing() {
}



	</pre>
	     </td>
	   </tr>
	 </table>

	 <pre><b>$</b> clang -c main.c -o main.o
<b>$</b> file main.o
main.o: ELF 64-bit LSB relocatable, ARM aarch64, version 1 (SYSV), not stripped
<b>$</b> clang -c main.c -o main.o <span class="r">-flto</span>
<b>$</b> file main.o
<span class="r">hello.o: LLVM IR bitcode</span></pre>



<pre>
<b>$</b> clang main.c do_nothing.c <span class="r">-flto</span>
<b>$</b> time ./a.out

real	0m2.112s
user	0m2.107s
sys	0m0.004s
<b>$</b> clang <span class="b">-O3</span> <span class="r">-flto</span> -c hello.c -o hello.o

real	<span class="b">0m0.002s</span>
user	0m0.000s
sys	0m0.002s</pre>





<h2>Dialects</h2>

<h3>C++</h3>
<p>C++ keeps on evolving. There was <code>C++98</code>, then <code>C++03</code> , then <code>C++11</code>, then <code>C++14</code>, then <code>C++17</code>, then <code>C++20</code>, and now <code>C++23</code>. The default dialect of the compiler keeps on evolving. Checkout your compiler documentation and use the flag <code>-std</code> (e.g:<code>-std=c++11</code>) to make sure you are using the proper one.
</p>

<pre>
-std=c++11.  // clang
-std=gnu++11 // gcc
</pre>

<h3>C</h3>
<p>Likewise, C has been revised over the years. The standardized C from 1988 was updated by C89, C90, C95, C99, and lately C11. Flags are to be used to indicate which version is used.</p>

<pre>-std=c99
</pre>


<h2>Standard libraries</h2>

<h3>C Standard Library</h3>
	<p>The implementations of the C Standard Library, commonly called <code>libc</code>, are fairly consistent. Switching between BSD's <code>libc</code>, GNU's <code>glibc</code>, or Android's <code>bionic</code> should not yield surprises.
	</p>





<h3>STL</h3>
	<p>The STL is a different story. There are several implementations around and their implementations are always a source of discrepancies.
</p>
<p>
	 At the very least, try to use the STL of your toolkit (GNU's <code>libstdc++</code>, LLVM's <code>libc++</code>, and <code>Microsoft STL</code>). If you are working on portable code to run on multiple OS, try to use the same STL everywhere. That means using LLVM's <code>libc++</code>. 
</p>



<div class="t">
Operators <code>new</code>, <code>new[]</code>, <code>delete</code>, and <code>delete[]</code> are not "built-in" C++ language. They actually come from the header <code>&lt;new&gt;</code>. Furthermore, if you peek inside that header, you will see that <code>new</code> is <a href="https://github.com/llvm-mirror/libcxxabi/blob/master/src/stdlib_new_delete.cpp">implemented</a> with <code>malloc</code>.

<pre>
_LIBCXXABI_WEAK void * operator <span class="r">new</span>(std::size_t size) _THROW_BAD_ALLOC {

  if (size == 0)
      size = 1;

  void* p;
  while ((p = <span class="r">::malloc</span>(size)) == 0) {
      std::new_handler nh = std::get_new_handler();
      if (nh)
          nh();
      else
          break;
  }
  return p;
}
</pre>
</div>


<h2>Debugging</h2>
<p>For debuggers to work, you need to compile with debugging information. This is done with <code>-g</code>. Upon seeing this flag, the compiler will generate sections featuring DWARF sections (for ELF binaries, get it?).
</p>

<pre>// hello.cc

#include &lt;iostream&gt;

int main() {
    std::cout << "Hello World!";
    return 0;
}</pre>

<pre><b>$</b> clang++ hello.cc
<b>$</b> ll a.out
-rwxrwxr-x 1 leaf leaf  <span class="r">9576</span> Mar 22 19:14 a.out*
<b>$</b> clang++ <span class="r">-g</span> hello.cc	
<b>$</b> ll a.out
-rwxrwxr-x 1 leaf leaf <span class="r">21912</span> Mar 22 19:15 a.out*
</pre>

<p>The size difference is significant but it is much more telling to compare with a real world, large application such as <code>git</code>.</p>

<pre><b>$</b> sudo apt-get install dh-autoreconf libcurl4-gnutls-dev libexpat1-dev gettext libz-dev libssl-dev
<b>$</b> sudo apt-get install install-info  
<b>$</b> git clone git@github.com:git/git.git
<b>$</b> cd git
<b>$</b> make configure
<b>$</b> ./configure --prefix=/usr
<b>$</b> make all
<b>$</b> ls -l --block-size=M git
-rwxrwxr-x 137 leaf leaf <span class="r">18M</span> Apr  2 18:04 git
</pre>

<p><code>git</code>'s Makefile builds in debug mode by default. Let's remove the flag <code>-g</code> and build again to see the difference.</p>
<pre>
<b>$</b> git reset --hard
<b>$</b> git clean -f -x
<b>$</b> sed -i 's/-g //g' Makefile
<b>$</b> make clean
<b>$</b> make all
<b>$</b> ls -l --block-size=M git
-rwxrwxr-x 137 leaf leaf <span class="r">4M</span> Apr  2 18:04 git
</pre>

<p>A binary without debug information is 3x smaller (-12 MiB)!</p>

<div class="t"> If you are unsure what <code>make</code> is doing, you can run it in verbose mode to see every commands it executes via parameter <code>V=1</code>.
</div>

<h2>Strip</h2>
<p>Compiling with debug sections logically increases the size of the output. The opposite of this operation is to <code>strip</code> sections which are not useful. It can reduce further the size of an ELF file.</p>

<pre><b>$</b> clang -c -g -o hello.o hello.c
<b>$</b> ll hello.o
-rw-rw-r-- 1 leaf leaf <span class="r">3256</span> Apr  3 01:21 hello.o
$ readelf -S -W hello.o
There are 22 section headers, starting at offset 0x738:

Section Headers:
  [Nr] Name              Type            Address          Off    Size   ES Flg Lk Inf Al
  [ 0]                   NULL            0000000000000000 000000 000000 00      0   0  0
  [ 1] .strtab           STRTAB          0000000000000000 000611 000121 00      0   0  1
  [ 2] .text             PROGBITS        0000000000000000 000040 000034 00  AX  0   0  4
  [ 3] .rela.text        RELA            0000000000000000 000478 000048 18   I 21   2  8
  [ 4] .rodata.str1.1    PROGBITS        0000000000000000 000074 00000e 01 AMS  0   0  1
  [ 5] .debug_abbrev     PROGBITS        0000000000000000 000082 000038 00      0   0  1
  [ 6] .debug_info       PROGBITS        0000000000000000 0000ba 000037 00      0   0  1
  [ 7] .rela.debug_info  RELA            0000000000000000 0004c0 000060 18   I 21   6  8
  [ 8] .debug_str_offsets PROGBITS       0000000000000000 0000f1 00001c 00      0   0  1
  [ 9] .rela.debug_str_offsets RELA      0000000000000000 000520 000078 18   I 21   8  8
  [10] .debug_str        PROGBITS        0000000000000000 00010d 000050 01  MS  0   0  1
  [11] .debug_addr       PROGBITS        0000000000000000 00015d 000010 00      0   0  1
  [12] .rela.debug_addr  RELA            0000000000000000 000598 000018 18   I 21  11  8
  [13] .comment          PROGBITS        0000000000000000 00016d 000026 01  MS  0   0  1
  [14] .note.GNU-stack   PROGBITS        0000000000000000 000193 000000 00      0   0  1
  [15] .eh_frame         PROGBITS        0000000000000000 000198 000030 00   A  0   0  8
  [16] .rela.eh_frame    RELA            0000000000000000 0005b0 000018 18   I 21  15  8
  [17] .debug_line       PROGBITS        0000000000000000 0001c8 00005f 00      0   0  1
  [18] .rela.debug_line  RELA            0000000000000000 0005c8 000048 18   I 21  17  8
  [19] .debug_line_str   PROGBITS        0000000000000000 000227 000022 01  MS  0   0  1
  [20] .llvm_addrsig     LOOS+0xfff4c03  0000000000000000 000610 000001 00   E 21   0  1
  [21] .symtab           SYMTAB          0000000000000000 000250 000228 18      1  21  8
</pre>

<p>Now let's strip the object.</p>

<pre><b>$</b> strip hello.o
<b>$</b> ll hello.o
-rw-rw-r-- 1 leaf leaf <span class="r">816</span> Apr  3 01:22 hello.o
<b>$</b> readelf -S -W hello.o
There are 8 section headers, starting at offset 0x130:

Section Headers:
  [Nr] Name              Type            Address          Off    Size   ES Flg Lk Inf Al
  [ 0]                   NULL            0000000000000000 000000 000000 00      0   0  0
  [ 1] .text             PROGBITS        0000000000000000 000040 000034 00  AX  0   0  4
  [ 2] .rodata.str1.1    PROGBITS        0000000000000000 000074 00000e 01 AMS  0   0  1
  [ 3] .comment          PROGBITS        0000000000000000 000082 000026 01  MS  0   0  1
  [ 4] .note.GNU-stack   PROGBITS        0000000000000000 0000a8 000000 00      0   0  1
  [ 5] .eh_frame         PROGBITS        0000000000000000 0000a8 000030 00   A  0   0  8
  [ 6] .llvm_addrsig     LOOS+0xfff4c03  0000000000000000 0000d8 000001 00   E  0   0  1
  [ 7] .shstrtab         STRTAB          0000000000000000 0000d9 000051 00      0   0  1
</pre>

<p>Note that in this example, we used <code>strip</code> on an object file but since it works on ELF, it can be (and usually is) used on the linker output.</p>

<h1>Next</h1>
<hr/>
<p>
<a href="ld.php">The Linker (4/5)</a>
</p>

</div>

<?php include 'footer.php'?>