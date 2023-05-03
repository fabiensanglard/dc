<?php include 'header.php';?>


<h1>Introduction</h1>
<a class="arrow" style="visibility:hidden;" href=".">←</a> <a class="arrow" href="driver.php">→</a> 
<hr/>
<p>
I remember how pleasant it was to learn to program in C. There were so many good books explaining not only the language but also the standard library. I devoured both <a href="https://amzn.to/3xRCdbM">The C Programming Language (K&amp;R)</a> by Kernighan/Ritchie and <a href=https://amzn.to/3kseI66">The Standard C Library</a> by P.J. Plauger. Then came <a href="https://amzn.to/41AJdI1">Expert C Programming</a> by Van der Linden and finally <a href="https://amzn.to/3kImp8s">C: A Reference Manual</a> by Harbison and Steele.
</p>

<p>
It was an equally enjoyable experience when I took on C++. I found myself unable to put down the <a href="https://amzn.to/3IWgTIO">Effective C++</a> series by Scott Meyers. I loved the simple layout and the astute usage of red text for emphasis. I must have read half the first volume while standing in the bookstore before I left with the complete series under my arm. I remember power walking to my place to keep on reading.
</p>

<p>
A contrasting experience was to learn how to use the tools to turn my programs into executable. It was a painfully slow and deeply unpleasant process where knowledge was gathered here and there after trial, errors, and a lot of time spent on search engines. It felt like acquiring the same level of comfort to use a compiler took significantly more than learning the language.
</p>
<p>
I blame this experience on the lack of literature on the topic. Most language books start with a "Hello World" code sample. In the case of K&amp;R, it would be <code>hello.c</code>.
</p>

<pre>#include &lt;stdio.h&gt;

main()
{
    printf("hello, world\n");
} </pre>

<p>
The reader is given the command to convert that text file into an executable and it is the last time they will hear about how to use the compiler.
</p>
<pre><b>$</b> cc hello.c
<b>$</b> ./a.out
hello, world</pre> 

<p>
This is the gap this series attempts to fill. It won't teach about a language, its libraries, or an SDK. It won't teach how to write a compiler or a linker either. These articles are meant to ease leaving the world of one-file examples. It is a pot-pourri of the things I wish I had known when I was pulling my hair over mysterious <span class="r">LNK2019</span> and other <span class="r">LNK4002</span> errors.</p>

<p> Here will be explained the core concepts associated with the creation of an executable. As much as possible claims will be backed up with reproducible steps relying on  <code>bintools</code> and driver verbose mode (<code>-v</code>). The goal is to provide the readers with both the tools and a mental map to explore beyond the charted territories of these pages.
</p>

<b><u>Note:</u></b> Some liberties were taken with command invocation outputs for typesetting purposes. Commands such as <code>clang -v</code> generate a lot more than what is actually printed here. The uninteresting parts were removed to only keep what is relevant to the topic at hand. Rest assured that no command-line tools were hurt during the making of this text.
</p>


<h2>Environment</h2>
<p> The examples assume a Linux platform. Depending on which illustrate the topic better, <code>gcc</code> or <code>clang</code> compiler drivers are used. If you are using Mac OS X or Windows, the ideas and concepts should be similar. Here is a table of equivalencies.
</p>

<table class="lined" style="width:100%;">
	<tr>
		<th>Platform</th>
		<th>Driver</th>
		<th>Object format</th>
		<th>Dynamic library</th>
		<th>Static library</th>
		<th>Executable</th>
	</tr>

	<tr>
		<td>Linux</td>
		<td>gcc</td>
		<td>elf</td>
		<td>library.so</td>
		<td>library.a</td>
		<td>elf</td>
	</tr>

	<tr>
		<td>Mac OS X</td>
		<td>clang</td>
		<td>macho</td>
		<td>library.dylib</td>
		<td>library.a</td>
		<td>macho</td>
	</tr>

	<tr>
		<td>Windows</td>
		<td>LE.EXE</td>
		<td>COFF</td>
		<td>library.dll</td>
		<td>library.lib</td>
		<td>PE</td>
	</tr>

</table>
<p>The platform / toolchain association is the one most often encountered but they may vary. For example, <code>clang</code> is available on all platforms and <code>gcc</code> is available on Windows via cygwin.
</p>



<h2>Structure</h2>
<div style="width:40%;float: right; margin-left: 2ch; margin-bottom: 1ch;">
<table class="lined" style="width: 100%; text-align: center; margin-top: 0;">
  <tr>
<td colspan="3">1. driver
		</td><td style="border-top-style: hidden; border-right-style: hidden;"></td>
  </tr>

  <tr>
    <td>2. cpp</td>
    <td>3. cc</td>
    <td>4. ld</td>
    <td>5. loader</td>
  </tr>
</table>
</div>

<p style="margin-top: 0;">This series is divided into five parts. First is explained the component which rules them all, the compiler driver <b>(1)</b>. Then we drill into the three stages of the compilation pipeline, detailing their inputs/outputs. The pre-processor <b>(2)</b>, <code>cpp</code>, converts source code files into translation units (TU), is covered first. Then comes the compiler <code>cc</code> <b>(3)</b>, which ingests TUs and outputs relocatable (object) files. Then we look at the element combining all objects together into and executable, the linker <code>ld</code> <b>(4)</b>.</p>

<p> In the fifth and final part we take a look at the linux loader (also called <code>ld</code>) <b>(5)</b> to further understand the linker output.
</p>

<h1>Next</h1>
<hr/>
<p>
	<a href="driver.php">The Compiler Driver (1/5)</a>

</p>
<?php include 'footer.php'?>
