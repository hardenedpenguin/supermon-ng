<?php

// Mapping ini files to specific username defined ini files.
//
// D. Crompton, WA3DSP 4/2021
//
// This file maps individual ini files (formerly just allmon.ini)
// to a dedicated ini file per login username. If this file does
// not exist or the contents of this file are just comments as
// shown in the examples below then the default allmon.ini file
// is used for ALL logins.
//
// Change ONLY the username as shown below in the examples as
// 'admin', 'doug', 'wa3dsp' to the usernames you want to map
// to specificly formatted ini files which are entered between
// the double quotes for each entry. DO NOT change any other 
// syntax in the line. The // at the beginning makes it a 
// comment and would not appear on a normal line.

// EXAMPLES -
//$ININAME['admin'] = "admin-allmon.ini";
//$ININAME['doug'] = "doug-allmon.ini";
//$ININAME['wa3dsp'] =  "wa3dsp-allmon.ini";

// You should have at least one entry to facilitate changing your
// admin login ini file name. Here it is shown as just mapping to
// the allmon.ini file but you can customize that. It is suggested 
// that you name the files username-allmon.ini and they would always
// go in the supermon2/user_files directory. 

// NOTE if the file user_files/nolog.ini exists it will be used when
// there is no login to supermon2. If you want the nolog users to have
// the allmon.ini or some other ini simply copy it to user_files/nolog.ini

$ININAME['admin'] = "admin-allmon.ini";
$ININAME['user1'] = "user1-allmon.ini";

?>
