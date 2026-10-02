<?php
//USE ;end TO SEPERATE SQL STATEMENTS. DON'T USE ;end IN ANY OTHER PLACES!

$sql = [];
$count = 0;

//v1.0.4
$sql[$count][0] = '1.0.4';
$sql[$count][1] = '-- First version with a CHANGEDB, nothing to update';

//v1.0.5
++$count;
$sql[$count][0] = '1.0.5';
$sql[$count][1] = "
DELETE gibbonPermission FROM gibbonPermission JOIN gibbonAction ON (gibbonAction.gibbonActionID=gibbonPermission.gibbonActionID) WHERE gibbonAction.name='Manage Course Programs' AND gibbonAction.gibbonModuleID=(SELECT gibbonModuleID FROM gibbonModule WHERE name='Transcripts');end
DELETE FROM gibbonAction WHERE name='Manage Course Programs' AND gibbonModuleID=(SELECT gibbonModuleID FROM gibbonModule WHERE name='Transcripts');end
UPDATE gibbonAction SET URLList='transcripts_view.php, transcript_print.php, transcripts_gradeAjax.php, transcripts_cleanup.php' WHERE name='Generate Transcripts_all' AND gibbonModuleID=(SELECT gibbonModuleID FROM gibbonModule WHERE name='Transcripts');end
CREATE TABLE IF NOT EXISTS `gibbonTermAlias` (`gibbonTermAliasID` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT, `gibbonSchoolYearTermID` INT(10) UNSIGNED NOT NULL, `ecclesiasticalName` VARCHAR(50) NOT NULL, `secularAlias` VARCHAR(50) NOT NULL, `notes` VARCHAR(255) DEFAULT NULL, PRIMARY KEY (`gibbonTermAliasID`), UNIQUE KEY `gibbonSchoolYearTermID` (`gibbonSchoolYearTermID`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;end
";

//v1.0.6
++$count;
$sql[$count][0] = '1.0.6';
$sql[$count][1] = "
ALTER TABLE `gibbonStudentProgramHistory` DROP COLUMN `concentration`;end
UPDATE gibbonAction SET description='Manage program start, switch, and graduation dates.' WHERE name='Manage Student Programs' AND gibbonModuleID=(SELECT gibbonModuleID FROM gibbonModule WHERE name='Transcripts');end
";

//v1.0.7
++$count;
$sql[$count][0] = '1.0.7';
$sql[$count][1] = "
ALTER TABLE `gibbonStudentProgramHistory` DROP COLUMN `studentLevel`;end
";
