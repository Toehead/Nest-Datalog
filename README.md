=============

Introduction
-------------
This is an expansion of Nest Extended, by Matt Hirschfelt, and relies on the same projects as his version
<a href="https://github.com/MattHirschfelt/Nest-Extended">Nest Extended</a>

The purpose of this project is to datalog from the Nest Thermostat, as well as provide some options (such as active humidity control or circulation mode) that expand on the Nest capabilities. 

Additions/Changes in Nest Datalog:
- The data has been reformatted to be easier to read. In addition, this version now relies on the Flot "Labels" library by Matt Burland in order to provide axis labes and units. 
- I have added support for heat pumps and system configuration. Nest Datalog now reports Aux/Regular heat, Aux threhsold temperatures and system level configuration. The last measurement is displayed at the top of the page for easy reading. This also requires modifying the NEST PHP API.
- A data filtering algorithm has been added that discards nonsense values that are often returned by the nest call. This improves the continuity of the data. 
- The degree day algorithm has been changed to get data for multiple days. This improves the robustness of the algorithm and helps prevent gaps in the data. 
- All changes have been commented to allow for easier modifications in the future
- Circulation mode has been added for users with wood stoves. This detects when the stove is active, and periodically turns on the fan to circulate warm air through the house.



Installation
-------------
instructions are a work in progress
