<?php

// Mapping ini files to specific username defined ini files.

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

$ININAME['admin'] = "allmon.ini";

?>
