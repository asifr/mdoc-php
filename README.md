mdoc
========
By [Asif Rahman](http://neuralengr.com/members/asif-rahman)

MATLAB m-file to HTML documentation generator

Literate programming is a method of crafting software that places emphasis on the way that people understand the problem a particular piece of code is attempting to solve, rather than the way that computers interpret the source code. This tool generates a literate-style documentation from MATLAB code. It produces HTML that displays your comments alongside your code.

## Instructions

- Upload a MATLAB m-file
- Download the HTML file

## Usage Notes

The program will look for commented lines and use this to generate the documentation. However, note that comments inline with code won't be parsed because the philosophy guiding literate programming is the realization that the documentation describes the chunk of code.

Documentation is formatted using Markdown (see below) but HTML is also allowed in the comments. LaTeX should be wrapped in `$$` tags: e.g. `$$\frac{dV}{dt}$$`