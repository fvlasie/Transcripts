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

//v1.0.8
++$count;
$sql[$count][0] = '1.0.8';
$sql[$count][1] = "
CREATE TABLE IF NOT EXISTS `gibbonTranscriptProgram` (`gibbonTranscriptProgramID` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT, `name` VARCHAR(30) NOT NULL, `sequenceNumber` INT NOT NULL DEFAULT 0, PRIMARY KEY (`gibbonTranscriptProgramID`), UNIQUE KEY `name` (`name`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;end
INSERT IGNORE INTO gibbonTranscriptProgram (name, sequenceNumber) VALUES ('MTS', 1), ('BTh', 2), ('Certificate', 3), ('Iconography', 4), ('Iconology', 5), ('Gap-Year', 6), ('Non-Degree', 7);end
ALTER TABLE `gibbonStudentProgramHistory` MODIFY `programType` VARCHAR(30) NOT NULL;end
";

//v1.0.9
++$count;
$sql[$count][0] = '1.0.9';
$sql[$count][1] = "
UPDATE gibbonModule SET category='Registrar' WHERE name='Transcripts';end
UPDATE gibbonAction SET name='Transcripts_all', category='Registrar' WHERE name='Generate Transcripts_all' AND gibbonModuleID=(SELECT gibbonModuleID FROM gibbonModule WHERE name='Transcripts');end
UPDATE gibbonAction SET name='Transcripts_myStudents', category='Registrar' WHERE name='Generate Transcripts_myStudents' AND gibbonModuleID=(SELECT gibbonModuleID FROM gibbonModule WHERE name='Transcripts');end
UPDATE gibbonAction SET name='Transcripts_myTranscript', category='Registrar' WHERE name='Generate Transcripts_myTranscript' AND gibbonModuleID=(SELECT gibbonModuleID FROM gibbonModule WHERE name='Transcripts');end
UPDATE gibbonAction SET name='Program Dates Management', category='Registrar' WHERE name='Manage Student Programs' AND gibbonModuleID=(SELECT gibbonModuleID FROM gibbonModule WHERE name='Transcripts');end
UPDATE gibbonAction SET name='Registrar Reports', category='Registrar' WHERE name='Advanced Registrar Reports' AND gibbonModuleID=(SELECT gibbonModuleID FROM gibbonModule WHERE name='Transcripts');end
UPDATE gibbonAction SET name='Transcript Template', category='Registrar' WHERE name='Manage Transcript Template' AND gibbonModuleID=(SELECT gibbonModuleID FROM gibbonModule WHERE name='Transcripts');end
UPDATE gibbonSetting SET value=CONCAT(value, ',Registrar') WHERE scope='System' AND name='mainMenuCategoryOrder' AND FIND_IN_SET('Registrar', value)=0;end
";

//v1.0.10
++$count;
$sql[$count][0] = '1.0.10';
$sql[$count][1] = "
UPDATE gibbonModule SET category='Assess' WHERE name='Transcripts';end
UPDATE gibbonSetting SET value=TRIM(BOTH ',' FROM REPLACE(CONCAT(',', value, ','), ',Registrar,', ',')) WHERE scope='System' AND name='mainMenuCategoryOrder' AND FIND_IN_SET('Registrar', value)>0;end
";

//v1.0.11
++$count;
$sql[$count][0] = '1.0.11';
$sql[$count][1] = "
INSERT INTO gibbonAction (gibbonModuleID, name, precedence, category, description, URLList, entryURL, entrySidebar, menuShow, defaultPermissionAdmin, defaultPermissionTeacher, defaultPermissionStudent, defaultPermissionParent, defaultPermissionSupport, categoryPermissionStaff, categoryPermissionStudent, categoryPermissionParent, categoryPermissionOther)
VALUES ((SELECT gibbonModuleID FROM gibbonModule WHERE name='Transcripts'), 'Course Details', 5, 'Registrar', 'Set course level, mode of instruction, and concentration for a school year.', 'course_detail_manage.php, course_detail_manageProcess.php', 'course_detail_manage.php', 'Y', 'Y', 'Y', 'N', 'N', 'N', 'N', 'Y', 'N', 'N', 'N');end
INSERT INTO gibbonPermission (gibbonRoleID, gibbonActionID) VALUES ('001', (SELECT gibbonActionID FROM gibbonAction JOIN gibbonModule ON (gibbonAction.gibbonModuleID=gibbonModule.gibbonModuleID) WHERE gibbonModule.name='Transcripts' AND gibbonAction.name='Course Details'));end
";

//v1.0.12
++$count;
$sql[$count][0] = '1.0.12';
$sql[$count][1] = "
UPDATE gibbonAction SET name='Program Management' WHERE name='Program Dates Management' AND gibbonModuleID=(SELECT gibbonModuleID FROM gibbonModule WHERE name='Transcripts');end
";

//v1.0.13
++$count;
$sql[$count][0] = '1.0.13';
$sql[$count][1] = "
UPDATE gibbonAction SET URLList='program_manage.php, program_manageProcess.php, program_manage_add.php, program_manage_edit.php, program_manage_editProcess.php, program_type_add.php, program_type_addProcess.php, program_type_delete.php, program_type_deleteProcess.php' WHERE name='Program Management' AND gibbonModuleID=(SELECT gibbonModuleID FROM gibbonModule WHERE name='Transcripts');end
";
