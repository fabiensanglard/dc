<html>
<head>
<style>
	* {
		font-size: 100%;
	}
	html {
	max-width: 900px;
	font-family : Verdana, "Bitstream Vera Sans", "DejaVu Sans", Tahoma, Geneva, Arial, Sans-serif;
	text-align: justify;
	hyphens: auto;
	margin: 0 auto;
    padding-left: 2%;
    padding-right: 3%;
    color:#444;
}

pre {
	background-color: #EEE;
	border: solid black 1px;
	padding: 1ch;
	padding-left:2ch;
	overflow: scroll;
}

code {
/*	font-size: 110%;*/
	font-weight: bold;
	background-color: #e1e1e1;
	border-radius: 0.5ch;
	padding-left: 0.3ch;
	padding-right: 0.3ch;
}

img {
	border: 1px solid black;
}

h1 {
	page-break-before: right;
	font-weight: bold;
	text-transform: uppercase;
	font-size: 140%;
	margin-top: 3ch;
	margin-bottom: 0.5ch;
}

h2 {
	margin-top: 3ch;
}

h3 {
	color: #999;
}
.lined th {
	background-color: #EEE;
	

}
table.lined, .lined th, .lined td{
   border: 1px solid black; 
   border-collapse: collapse; 
   border-spacing : 0x;
   text-align: center;
}

table {
	margin-top: 2ch;
	width: 100%;
}

td pre {
	vertical-align: top;
}

span.r{
	color:red;
	font-weight: bold; 
}

span.g{
	color:green;
	font-weight: bold; 
}

span.b{
	color:blue;
	font-weight: bold; 
}

span.h{
	font-weight: bold; 
}

span.k{
	font-weight: bold; 
	color:orange;
}

div.t {

	padding-left: 1ch;
	border-left: solid 1px black;
	margin-bottom: 2ch;
}
div.t:before {
	content: "Trivia:";
	white-space: nowrap;
	text-decoration: underline;
	font-weight: bold; 
}

  	a {
      color:#444;
      font-weight: bold;
    }

.arrow {
	text-decoration: none;
}    

.center {
  display: block;
  margin-left: auto;
  margin-right: auto;
}

</style>

<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=12.0, minimum-scale=1.0, user-scalable=yes">

<title>Driving Compilers</title>
</head>
<body>
	<a href="/">
<h1 style="text-align: center; margin-bottom:4ch;padding-top: 1ch;">Driving Compilers</h1>
</a>

<p style="float:left;font-size: 90%;">
By <b>Fabien Sanglard</b><br/>
May 3rd, 2023<br/>
</p>
<p style="float:right; font-size: 90%;padding-bottom: 4ch;text-align: center;">
<a href="https://github.com/fabiensanglard/dc">Mistake - Suggestion <br/> Feedback</a>
</p>
<div style="clear:both;">