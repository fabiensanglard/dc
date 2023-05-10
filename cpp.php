<?php include 'header.php';?>



<h1>The Preprocessor (2/5)</h1>
<a class="arrow" href="driver.php">←</a> <a class="arrow" href="cc.php">→</a> 
<hr/>


<div style="width:30%;float: right; margin-left: 2ch; margin-bottom: 2ch;">
<table class="lined" style="width: 100%; text-align: center; margin-top: 0;">
  <tr>
   <td colspan="3">driver
    </td><td style="border-top-style: hidden; border-right-style: hidden;"></td>
  </tr>

  <tr>
    <td>cpp<span class="r">*</span></td>
    <td>cc</td>
    <td>ld</td>
    <td>loader</td>
  </tr>
</table>
</div>

<p style="margin-top: 0;">
In this chapter, we no longer focus on the compiler driver. Instead we take a look at the first stage of the compilation pipeline, the preprocessor.
</p>
<p>
The goal of the preprocessor is to ingest one source file to resolve all its header files dependencies, resolve all macros, and output a translation unit that will be consumed by the compiler in the next stage. The preprocessor usually takes care of a source file (<code>.c</code>/<code>.cc</code>/<code>.cpp</code>/<code>.m</code>/<code>.mm</code>) but it is language agnostic. It can process anything, even text files, as long as it detects "directives" (commands starting with <code>#</code> character).
</p>


<h2>Is it <code>cpp</code> or <code>-E</code> ?</h2>

<p>
In the early days, the preprocessor was a separate executable called <code>cpp</code> (<b>C</b> <b>P</b>re<b>P</b>rocessor). Lucky us, developers have maintained it all these years, and we can still invoke it. 
</p>



<pre><b>$</b> <span class="r">cpp</span> hello.c -o hello.tu	
</pre>
<p>Just kidding. Using verbose mode shows that it is once again the compiler driver which uses <code>argv[0]</code> to detect it should invoke itself with <code>-E</code> parameter to behave like a preprocessor.
</p>
<pre>
<b>$</b> <span class="r">cpp</span> -v hello.c -o hello.tu
clang -cc1 <span class="r">-E</span> -o hello.tu hello.c  
</pre>  

<div class="t">The extension used by compilers to store translation units can be <code>.i</code>, <code>.ii</code>, <code>.mi</code>, or even <code>.mii</code>. For simplicity, we always use <code>.tu</code>.</div>

<h2>How much pre-processing occurs?</h2>
<p>
A lot of work is done by <code>cpp</code>! So much in fact that the preprocessor is a bottleneck in big projects. Just to get an idea, see how the six lines in hello.c become a behemote 748 lines translation unit. 
</p>
<pre><b>$</b> <span class="r">wc -l</span> hello.c
 <span class="r">6</span>  hello.c</pre>

<pre><b>$</b> cpp hello.c > hello.tu
<b>$</b> <span class="r">wc -l</span> hello.tu
 <span class="r">748</span>  hello.tu</pre>

<div class="t"> Including whole headers for each translation unit generation is a repeating task that is wasteful. Not only a huge volume of lines is involved, each of them are tokenized by <code>cpp</code>. C++ modules should solve this problem. They are not available yet but we should have them soon, right after hell freezes over.
</div>

<h2>Peeking inside a translation unit</h2>
<p>Let's look inside a translation unit.
</p>
<pre><b>$</b> cpp hello.c > hello.tu
<b>$</b> cat hello.tu
<span class="r"># 328 "/usr/include/stdio.h" 3 4</span>
extern int <span class="b">printf</span> (const char *__restrict __format, ...);
... // many hundred more lines
<span class="r"># 2 "hello.c" 2</span>
main()
{
    <span class="b">printf</span>("hello, world\n");
}

</pre>

<p> Each fragment of code is preceded by a comment <code># linenum filename flags</code> allowing to backtrack which file it came from. This allows the compiler to issue error messages with accurate line numbers.</p>


<h2>Directives, the preprocessor language</h2>
<p>
All directives aimed at the preprocessor are prefixed with <code>#</code>. The names are usually self-explanatory. You can among many features include files, declare macro, perform conditional compilation.
<div class="t">
The preprocessor is regularly abused. It is so powerful that you can use it to have C program look like Java.
  <pre>#include &lt;iostream&gt;
#define System S s;s
#define public
#define static
#define void int
#define main(x) main()
struct F{void println(char* s){std::cout << s << std::endl;}};
struct S{F out;};

public static void main(String[] args) {
  System.out.println("Hello World!");
}
</pre>
</div>
 <p>
 A handy feature of the pre-processor is the ability to receive command-line parameters to define values via the <code>-D</code> flag. Let's build a modified hello world.
</p>
<pre>// defined_return_code.c

int main() {
  return <span class="r">RETURN_VALUE</span>;
}</pre>
<p>Let's compile it while defining <code>RETURN_VALUE</code> with <code>-DRETURN_VALUE=3</code>
<pre><b>$</b> clang <span class="r">-DRETURN_VALUE=3</span> defined_return_code.c
<b>$</b> ./a.out ; echo $?
<span class="r">3</span>
</pre>


<div class="t">
  VLC is an immensely popular open source video player entirely written in C. It uses the pre-processor macros to implement <code>struct</code> inheritance.
<pre>/* VLC_COMMON_MEMBERS : members common to all basic vlc objects */
#define <span class="r">VLC_COMMON_MEMBERS</span>                                                  \
const char *psz_object_type;                                                \
                                                                            \
    /* Messages header */                                                   \
    char *psz_header;                                                       \
    int  i_flags;                                                           \
                                                                            \

struct libvlc_int_t {
  <span class="r">VLC_COMMON_MEMBERS</span>

  /* Everything Else */
}
</pre>
</div>

<h2>Why headers are needed</h2>
<p>When C was created, in the 70s, memory was severely limited. So much so that it constrained compilers to emit instructions after a single pass over a source file. To achieve one pass emission, the language designers pushed the constraint on the programmer. 
</p>
<p>All functions and variables must be declared before using them. Their definition could come later.</p>
<pre>
int mul(int x, int y);                 // This is a declaration
int mul(int x, int y) <span class="r">{ return x * y;}</span> // This is a definition (and also a declaration)	

extern int i;                          // This is a declaration
int i = 0 ;                            // This is a definition (and also a declaration) 	

class A;                               // This is a (forward) declaration
class A {};                            // This is a definition (and also a declaration) 	
</pre> 

Let's see what happens when we disregard this constraint.
</p>
<pre>// var_err.c

int main() {
  return v;
}

int v = 0;
</pre>

<p> In this example, despite variable <code>v</code> being defined three lines later, the compiler will emit and error when <code>main</code> attempts to use it.</p>

<pre>
<b>$</b> clang var_err.c
<span class="r">var_err.c:2:10: error: use of undeclared identifier 'v'</span>
</pre> <p>
In the case of function invocation, the compiler not only needs to know the return type but also the parameters a function expects (a.k.a its signature). It does not matter if the actual method body (definition) comes after as long as the parameters and their types are known when the callsite must be issued. 
</p>

<pre>// bad_fibonacci.c

int fibonacci(int n) {
  if (n <= 1)
    return n;
  return fibonacci(n - 1) + fibonacci(n - 2);
}
</pre>
<pre>
<b>$</b> clang -c bad_fibonacci.c
<span class="r">bad_fibonacci.c:4:12: error: implicit declaration of function 'fibonacci' is invalid in C99</span>
</pre>	
<p><code>bad_fibonacci</code> did not declare the function <code>fibonacci</code> before using it which resulted in an error.</p>

<pre>//  good_fibonacci.c

<span class="r">int fibonacci(int n);</span>

int fibonacci(int n) {
  if (n <= 1)
    return n;
  return fibonacci(n - 1) + fibonacci(n - 2);
}</pre>


<p> Adding the definition allows the compiler to work in one pass.</p>

<pre>
<b>$</b> clang -c good_fibonacci.c // It worked
</pre>



<h2>Things get complicated</h2>

<p>The rule of definition before usage is simple but inconvenient to follow. Sometimes it is plain impossible when two functions call each other.
</p>
<pre>
int <span class="r">function1</span>(int x) {
  if (x) return <span class="b">function2</span>(2);
  return 1;
}

int <span class="b">function2</span>(int x) {
  if (x) return <span class="r">function1</span>(2);
  return 2;
}</pre>

<p>The solution is to adopt a convention where definitions are put in header files and the declaration in the source files. This way, programmers are free to organize their source code as they please.</p>


<table style="width: 100%;">

<tr>
<td>
<pre>// foo.h

int <span class="r">mul</span>(int x, int y);	
int <span class="g">sub</span>(int x, int y);
</pre>
</td>
<td>
<pre>// bar.h

int <span class="k">div</span>(int x, int y);	
int <span class="b">add</span>(int x, int y);
</pre>
</td>
</tr>

<tr>
<td>
<pre>// foo.c

#include "foo.h"
#include "bar.h"

int <span class="r">mul</span>(int x, int y) {
  int c = x;
  while(y--) // mul with add!
    c = <span class="b">add</span>(1, c);
  return c;  
}

int <span class="g">sub</span>(int x, int y) {
  return x - y;
}

</pre>
</td>
<td>
<pre>// bar.c

#include "bar.h"
#include "foo.h"

int <span class="k">div</span>(int x, int y) {
  int c = x;
  while(y--) // div with sub!
    x = <span class="g">sub</span>(x, 1);
  return c;  
}

int <span class="b">add</span>(int x, int y) {
  return x + y;	
}

</pre>
</td>
</tr>


</table>

<p>
We can verify that this technique makes sense by looking at <code>cpp</code> outputs.
<p>
<pre>
$ <span class="r">cpp</span> foo.c > foo.tu
$ <span class="r">cpp</span> bar.c > bar.tu
</pre>
<p>
Notice how all comments have been removed and the macros resolved. All that remains is pure code. And of course, all functions are declared before being used.  
</p>
<table style="width: 100%;">

<tr>
<td>
<pre>// foo.tu

int <span class="r">mul</span>(int x, int y);  
int <span class="b">add</span>(int x, int y);

int <span class="k">div</span>(int x, int y);  
int <span class="g">sub</span>(int x, int y);

int <span class="r">mul</span>(int x, int y) {
  int c = x;
  while(y--)
    c = <span class="b">add</span>(1, c);
  return c;
}

int <span class="b">add</span>(int x, int y) {
  return x + y; 
}
</pre>
</td>
<td>
<pre>// bar.tu

int <span class="k">div</span>(int x, int y);
int <span class="g">sub</span>(int x, int y);

int <span class="r">mul</span>(int x, int y);  
int <span class="b">add</span>(int x, int y);

int <span class="k">div</span>(int x, int y) {
  while(y--)
    x = <span class="g">sub</span>(x, 1);
  return c;
}


int <span class="g">sub</span>(int x, int y) {
  return x - y;
}
</pre>
</td>
</tr>
</table>




<h2>Header guards</h2>
<p>
So far, it looks like the header system works well. Each source file becomes a translation unit with all declarations at the top. But this technique has a flaw if a header ends up being included more than once. Let's take the example of a mini game-engine project.
</p>

<table>
<tr>
<td>
<pre>// engine.h

struct World {
}; 





</pre>
</td>
<td>
<pre>// ai.h
 
#include "engine.h"



void think(World& world);


</pre>
</td>
<td>
  <pre>// render.h

#include "engine.h"



void render(World& world);


</pre>
</td>
</tr>


<tr>
<td>
<pre>// engine.cc

#include "engine.h"
#include "render.h"
#include "ai.h"

void hostFrame(World& world) {
  think(world);
  render(world);
}

</pre>
</td>


<td>
<pre>// ai.cc

#include "ai.h"



void think(World& world) {


}

</pre>
</td>
<td>
  <pre>// render.cc

#include "render.h"



void render(World &worldv) {


}
  </pre>
</td>

</tr>
</table>
<p>If we attempt to generate each object file, the sub-systems <code>ai.cc</code> and <code>render.cc</code> compile fine but <code>engine.cc</code> throws an error.
</p>

<pre><b>$</b> clang -c -o render.o render.cc
<b>$</b> clang -c -o ai.o ai.cc  
<b>$</b> clang -c -o engine.o engine.cc
In file included from engine.cc:4:
In file included from ./ai.h:3:
<span class="r">./engine.h:3:8: error: redefinition of 'World'
struct World {
       ^</span>
./render.h:3:10: note: './engine.h' included multiple times, additional include site here
#include "engine.h"
         ^
./ai.h:3:10: note: './engine.h' included multiple times, additional include site here
#include "engine.h"
         ^
./engine.h:3:8: note: unguarded header; consider using #ifdef guards or #pragma once
struct World {
       ^
1 error generated.
</pre>

<p>
Inspecting the resulting TUs with <code>cpp</code> shows the problem.
</p>
<table>
<tr>
<td>
<pre><b>$</b> cpp engine.cc

<span class="r">struct World {
};</span> 


void render(World& world);


<span class="r">struct World {
};</span> 


void think(World& world);


void hostFrame(World& world) {
  think(world);
  render(world);
}
</pre>
</td>
<td>
<pre><b>$</b> cpp ai.cc

struct World {
}; 









void think(World& world);


void think(World& world) {

}

</pre>
</td>
<td>
<pre><b>$</b> cpp render.cc

struct World {
}; 


void render(World& world);









void render(World &world) {

}

</pre>
</td>
</tr>
</table>

<p><code>engine.cc</code> includes <code>engine.h</code>. However <code>engine.cc</code> also includes <code>ai.h</code> which in turns also includes <code>engine.h</code>. In the final <code>cpp</code>ed translation unit, <code>engine.h</code> is included twice and the struct <code>World</code> is declared twice.
</p>

<p>
The solution to multiple import and import cycles is to use include guards or <code>pragma</code> guard. The difference between the two is that pragma is not part of the standard (although widely supported).
</p>




<table>
<tr>
<td>
<pre>// engine.h
<span class="r">#pragma once</span> // Pragma guard

struct World {
}; 





</pre>
</td>
<td>
<pre>// ai.h
<span class="r">#ifndef AI.H</span> // Header guard
<span class="r">#define AI.H</span> 
#include "engine.h"



void think(World& world);

<span class="r">#endif </span> // AI.H
</pre>
</td>
<td>
  <pre>// render.h
<span class="r">#ifndef RENDERER.H</span> // Header guard
<span class="r">#define RENDERER.H</span>
#include "engine.h"



void render(World& world);
<span class="r">
#endif</span> // RENDERER.H
</pre>
</td>
</tr>


<tr>
<td>
<pre>// engine.cc

#include "engine.h"
#include "render.h"
#include "ai.h"

void hostFrame(World& world) {
  think(world);
  render(world);
}

</pre>
</td>


<td>
<pre>// ai.cc

#include "ai.h"



void think(World& world) {


}

</pre>
</td>
<td>
  <pre>// render.cc

#include "render.h"



void render(World &worldv) {


}
  </pre>
</td>

</tr>
</table>

<p>Since we now prevent muptile header inclusipon in the same TU, we can compile the whole project.</p>

<pre><b>$</b> clang -c -o render.o render.cc
<b>$</b> clang -c -o ai.o ai.cc  
<b>$</b> clang -c -o engine.o engine.cc
<b>$</b>
</pre>


<h2>Precompiled headers (PCH)</h2>
<p>
  As we alluded earlier while <code>wc</code>ing the outputs of <code>cpp</code>, the volume resulting from <code>#include</code> is huge. It is even worse in C++ where <code>hello.cc</code> 6 lines turned into 44,065 lines, a whopping 7,344% increase.
</p> 

<p>
  It is a non-trivial amount of work to parse all this text, even with modern Threadripper CPUs. Build time can be reduced by using pre-compiled headers. 
</p>
<pre>// all_header.h

#include "engine.h"
#include "ai.h"
#include "render.h"
</pre>

<p>Precompiled headers are super header containing all other headers and stored in binary form.
</p>
<pre><b>$</b> clang -cc1 all_header.h <span class="r">-emit-pch</span> -o <span class="r">all_header.pch</span>
</pre>

<p>With this approach, the source code does not need to <code>#include</code> anything anymore.

<table>
<tr>
<td>
<pre>// engine.cc

void hostFrame(World& world) {
  think(world);
  render(world);
}

</pre>
</td>


<td>
<pre>// ai.cc

void think(World& world) {


}

</pre>
</td>
<td>
  <pre>// render.cc

void render(World &world) {


}
  </pre>
</td>

</tr>
</table>	

<p>Compiling requires to give the path to the precompiled header to the driver.
</p>

<pre><b>$</b> clang -v <span class="r">-include-pch all_header.pch</span> -c render.cc ai.cc engine.cc
</pre>





<h2>Header Search Path</h2>
<p>
  By default, the preprocessor first attempts to locate the target of <code>#include</code> directives in the same directory as the source file. If that fails, the preprocessor goes though the "header search path". Let's take the example of a simple <code>hello_world.c</code> project which uses an include for the string value to <code>printf</code>
</p>

<table>
<tr>
<td>
<pre>// hello_with_include.c

#include "stdio.h"
#include "hello_with_include.h"	

int main() {
  printf(MESSAGE);
  return 0;
}
</pre>
</td>


<td>
<pre>// include_folder/hello_with_include.h

#define MESSAGE "Hello World!\n"






</pre>
</td>


</tr>
</table>

<p>If the header is not in the same directory, it cannot be found by the pre-processor. We get an error.</p>
<pre><b>$</b> find .
hello_with_include.c	
<span class="h">include_folder/</span>hello_with_include.h
<b>$</b> clang hello_with_include.c
<span class="r">hello_with_include.c:2:10: fatal error: 'hello_with_include.h' file not found
#include "hello_with_include.h" 
         ^~~~~~~~~~~~~~~~~~~~~~</span>
</pre>

<p>
  The algorithm to lookup the header search path is quite elaborated but well described on GNU gcc <a href="https://gcc.gnu.org/onlinedocs/gcc/Directory-Options.html">cpp documentation</a> page. To fix our example, we can add a directory to the path to search via <code>-I</code>.
</p>
<pre><b>$</b> clang <span class="r">-Iinclude_folder</span> hello_with_include.c
</pre>

<p>
  There are many more flags that can be passed to the driver to impact the header search path. Among them, <code>-sysroot</code>, <code>-iquote</code>, <code>-isystem</code> which impacts vary whether an <code>#include</code> directive uses quotes <code>"</code> or angled brackets (<code>&lt;</code> <code>&gt;</code>). There is even a <code>-sysroot</code> parameter which defines a whole toolchain featuring both the header search path and the library linker search path.
</p>  




<h3>Headers provided by the compiler toolchain</h3>
<p>Some headers are not provided via flags. These come with the compiler and are automatically added to the header search path. It is the case of <code>stddef.h</code> which provides among other things <code>size_t</code> definition and the <code>NULL</code> macro.

</p>

<pre><b>$</b> cat someheader.h
#include "stddef.h"
<b>$</b> gcc <span class="r">-E</span> someheader.h
<span class="r"># 1 "/usr/lib/gcc/aarch64-linux-gnu/11/include/stddef.h" 1 3 4</span>
<b>$</b> clang <span class="r">-E</span> someheader.h
<span class="r"># 1 "/usr/lib/llvm-14/lib/clang/14.0.0/include/stddef.h" 1 3</span>
<b>$</b></pre>





<h2>Header dependency graph and build systems</h2>
<p>Build system compile source files over and over again. An obvious optimization is to re-use outputs from previous runs if they did not change.
  But because of the header search path and the undeclared preprocessor dependencies, it is hard to build a reliable dependency graph.
</p>
<p>
  This problem can be solved by asking the pre-processor which files were accessed while preprocessing. With <code>clang</code> for example, the flag  <code>-MD</code> requests the preprocessor to output the dependency required to generate a translation unit. A further option <code><code>-MF</code></code> instructs to write the output to a file. Note that these options can be fed to the compiler driver which will forward them to the pre-processor.
</p>
<pre>$ cat hello.c
#include &lt;stdio.h&gt;

int main() {
  printf("Hello, world!\n");
  return 0;
}
$ clang <span class="r">-MD -MF hello.d</span> -c -o hello.o hello.c
$ cat hello.d 
hello.o: hello.c /usr/include/stdio.h /usr/include/_types.h 
  /usr/include/sys/_types.h /usr/include/sys/cdefs.h 
  /usr/include/machine/_types.h /usr/include/i386/_types.h 
  /usr/include/secure/_stdio.h /usr/include/secure/_common.h
</pre>

<div class="t"> The notoriously blazing fast build system <code>ninja</code> leverages these dependencies outputs to create a dependency graph. After the first compilation, the dependency graph is parsed. On each subsequent build, file modification timestamps it checked to re-build only what has changed.
</div>


<h2>Header discipline</h2>
<p><code>cpp</code> will not warn programmers if they neglect to keep their headers tidy. As much as possible, try to:
<ul>
<li>Avoid transitive dependencies.</li>
<li>Not expose implementation details.</li>
</ul>
</p>
<p>The following header system exhibits the problems when these two rules are not followed.</p>

<table>
  <tr>
    <td>

    </td>
    </td>
    <td>
<pre>// utils.h

#pragma once

<span class="r">#include &lt;stdlib.h&gt;</span>

char* getBuffer(int size);
</pre>
    </td>    
  </tr>

    <tr>
    <td>
<pre>// main.c

#include "utils.h"

#include &lt;stdio.h&gt;

int main() {
  char* buffer = getBuffer(10);
  // do stuff ...
  free(buffer);
  return 0;
} 
</pre>
    </td>

    <td>
<pre>// utils.c  







char* getBuffer(int size) {
  return (char*)calloc(size);
} 

</pre>
    </td>    
  </tr>

</table>







<p>Header <code>utils.h</code> includes <code>stdlib.h</code> but it is wasteful. All source files including <code>utils.h</code> now have to also include <code>stdlib.h</code>. Moreover, the fact that <code>utils.c</code> uses <code>calloc</code> is an implementation detail. Let's refactor this line.</p>



<table>
  <tr>
    <td>

    </td>
   
    <td>
<pre>// util.h

#pragma once



char* getBuffer(int size);
</pre>
    </td>    
  </tr>

    <tr>
    <td>
<pre>// main.c

#include "utils.h"

#include &lt;stdio.h&gt;

int main() {
  char* buffer = getBuffer(10);
  // do stuff ...
  free(buffer);
  return 0;
} 
</pre>
    </td>
   
    <td>
<pre>// util.c  


<span class="r">#include &lt;stdlib.h&gt;</span>




char* getBuffer(int size) {
  return (char*)calloc(size);
} 

</pre>
    </td>    
  </tr>

</table>


<p>Moving <code>stdlib.h</code> include from the <code>.h</code> file to the <code>.c</code> file keeps the implementation details private. Let's see what happens when we try to compile.</p>

<pre><b>$</b> clang -o main main.c utils.c
<span class="r">main.c:2:3: warning: implicit declaration of function 'free' is invalid in C99 [-Wimplicit-function-declaration]
1 warning generated.
/usr/bin/ld: /tmp/main-3866ce.o: in function `main':
main.c:(.text+0x20): undefined reference to `free'  
</pre>

<p>The problem is that <code>main.c</code> has a transitive dependency on <code>stdlib.h</code>. The program compiled because <code>utils.h</code> included it. As soon as it was removed, the translation unit originating from <code>main.c</code>  fails to compile. The solution is to make all source files self-reliant without  transitive header dependencies. 

<table>
  <tr>
    <td>

    </td>
   
    <td>
<pre>// b.h

#pragma once



char* getBuffer(int size);
</pre>
    </td>    
  </tr>

    <tr>
    <td>
<pre>// main.c

#include "utils.h"
<span class="r">#include &lt;stdlib.h&gt;</span>
#include &lt;stdio.h&gt;

int main() {
  char* buffer = getBuffer(10);
  // do stuff ...
  free(buffer);
  return 0;
} 
</pre>
    </td>
   
    <td>
<pre>// b.c  


<span class="r">#include &lt;stdlib.h&gt;</span>




char* getBuffer(int size) {
  return (char*)calloc(size);
} 

</pre>
    </td>    
  </tr>

</table>


<h2>Know your libraries</h2>
<p>Modern IDEs automatically suggest a header to include if it is missing. This is a two edged sword because the project may become tied to a specific library without the programmer noticing. There are many C libraries (libc, STL versions, POSIX, Windows ...) and it is a good idea to know which header belongs to what.
</p>
 
<p>It is especially important if you are developing cross-platform. Header <code> unistd.h</code> for example, which defines POSIX functions, does not exist on Windows.
</p>

<h1>Next</h1>
<hr/>
<p>
<a href="cc.php">The Compiler (3/5)</a>
</p>


<?php include 'footer.php'?>
