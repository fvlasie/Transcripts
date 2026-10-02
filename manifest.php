<?php
// Manifest file for Transcripts module

$name        = 'Transcripts';
$description = 'Automated transcript generation and advanced registrar reporting.';
$entryURL    = 'transcripts_view.php';
$type        = 'Additional';
$version     = '1.0.14';
$author      = 'SPOTS Development Team';
$url         = 'https://spots.edu';
$category    = 'Assess';

$moduleTables = [
    "CREATE TABLE IF NOT EXISTS `gibbonStudentProgramHistory` (
        `gibbonStudentProgramHistoryID` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
        `gibbonPersonID` INT(10) UNSIGNED NOT NULL,
        `programType` VARCHAR(30) NOT NULL,
        `startDate` DATE NOT NULL,
        `switchDate` DATE DEFAULT NULL,
        `graduationDate` DATE DEFAULT NULL,
        `status` ENUM('Active', 'Switched', 'Graduated', 'Withdrawn', 'On Leave') NOT NULL DEFAULT 'Active',
        `notes` TEXT DEFAULT NULL,
        PRIMARY KEY (`gibbonStudentProgramHistoryID`),
        KEY `gibbonPersonID` (`gibbonPersonID`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "CREATE TABLE IF NOT EXISTS `gibbonTranscriptProgram` (
        `gibbonTranscriptProgramID` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(30) NOT NULL,
        `sequenceNumber` INT NOT NULL DEFAULT 0,
        PRIMARY KEY (`gibbonTranscriptProgramID`),
        UNIQUE KEY `name` (`name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "INSERT IGNORE INTO `gibbonTranscriptProgram` (`name`, `sequenceNumber`) VALUES ('MTS', 1), ('BTh', 2), ('Certificate', 3), ('Iconography', 4), ('Iconology', 5), ('Gap-Year', 6), ('Non-Degree', 7);",

    "CREATE TABLE IF NOT EXISTS `gibbonStudentInstructionMode` (
        `gibbonStudentInstructionModeID` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
        `gibbonPersonID` INT(10) UNSIGNED NOT NULL,
        `gibbonSchoolYearTermID` INT(10) UNSIGNED NOT NULL,
        `modeOfInstruction` ENUM('In-person', 'Remote') NOT NULL DEFAULT 'In-person',
        PRIMARY KEY (`gibbonStudentInstructionModeID`),
        UNIQUE KEY `personTerm` (`gibbonPersonID`, `gibbonSchoolYearTermID`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "CREATE TABLE IF NOT EXISTS `gibbonTermAlias` (
        `gibbonTermAliasID` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
        `gibbonSchoolYearTermID` INT(10) UNSIGNED NOT NULL,
        `ecclesiasticalName` VARCHAR(50) NOT NULL,
        `secularAlias` VARCHAR(50) NOT NULL,
        `notes` VARCHAR(255) DEFAULT NULL,
        PRIMARY KEY (`gibbonTermAliasID`),
        UNIQUE KEY `gibbonSchoolYearTermID` (`gibbonSchoolYearTermID`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
];

$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Transcripts', 'customAssetPath', 'Custom Asset Path', 'Relative folder for transcript PDF assets.', '/uploads/transcripts');";
$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Transcripts', 'page1BackgroundPath', 'Page 1 Background PDF', 'Vector PDF background for page one (logo and full header artwork).', '');";
$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Transcripts', 'page2BackgroundPath', 'Page 2 Background PDF', 'Vector PDF background for continuation pages.', '');";
$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Transcripts', 'registrarSignaturePath', 'Registrar Signature', 'Relative path to the registrar signature image.', '');";
$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Transcripts', 'registrarGibbonPersonID', 'Registrar User', 'Gibbon user who may generate official signed transcripts.', '');";

$actionRows[] = [
    'name'                      => 'Transcripts_all',
    'precedence'                => '3',
    'category'                  => 'Registrar',
    'description'               => 'View and export transcripts for any student.',
    'URLList'                   => 'transcripts_view.php, transcript_print.php, transcripts_gradeAjax.php, transcripts_cleanup.php',
    'entryURL'                  => 'transcripts_view.php',
    'defaultPermissionAdmin'    => 'Y',
    'defaultPermissionTeacher'  => 'N',
    'defaultPermissionStudent'  => 'N',
    'defaultPermissionParent'   => 'N',
    'defaultPermissionSupport'  => 'N',
    'categoryPermissionStaff'   => 'Y',
    'categoryPermissionStudent' => 'N',
    'categoryPermissionParent'  => 'N',
    'categoryPermissionOther'   => 'N',
];

$actionRows[] = [
    'name'                      => 'Transcripts_myStudents',
    'precedence'                => '2',
    'category'                  => 'Registrar',
    'description'               => 'View and export transcripts for students in your classes.',
    'URLList'                   => 'transcripts_view.php, transcript_print.php',
    'entryURL'                  => 'transcripts_view.php',
    'defaultPermissionAdmin'    => 'N',
    'defaultPermissionTeacher'  => 'Y',
    'defaultPermissionStudent'  => 'N',
    'defaultPermissionParent'   => 'N',
    'defaultPermissionSupport'  => 'N',
    'categoryPermissionStaff'   => 'Y',
    'categoryPermissionStudent' => 'N',
    'categoryPermissionParent'  => 'N',
    'categoryPermissionOther'   => 'N',
];

$actionRows[] = [
    'name'                      => 'Transcripts_myTranscript',
    'precedence'                => '1',
    'category'                  => 'Registrar',
    'description'               => 'View and export your own transcript.',
    'URLList'                   => 'transcripts_view.php, transcript_print.php',
    'entryURL'                  => 'transcripts_view.php',
    'defaultPermissionAdmin'    => 'N',
    'defaultPermissionTeacher'  => 'N',
    'defaultPermissionStudent'  => 'Y',
    'defaultPermissionParent'   => 'N',
    'defaultPermissionSupport'  => 'N',
    'categoryPermissionStaff'   => 'N',
    'categoryPermissionStudent' => 'Y',
    'categoryPermissionParent'  => 'N',
    'categoryPermissionOther'   => 'N',
];

$actionRows[] = [
    'name'                      => 'Program Management',
    'precedence'                => '2',
    'category'                  => 'Registrar',
    'description'               => 'Manage program start, switch, and graduation dates.',
    'URLList'                   => 'program_manage.php, program_manageProcess.php, program_manage_add.php, program_manage_edit.php, program_manage_editProcess.php, program_type_add.php, program_type_addProcess.php, program_type_delete.php, program_type_deleteProcess.php',
    'entryURL'                  => 'program_manage.php',
    'defaultPermissionAdmin'    => 'Y',
    'defaultPermissionTeacher'  => 'N',
    'defaultPermissionStudent'  => 'N',
    'defaultPermissionParent'   => 'N',
    'defaultPermissionSupport'  => 'N',
    'categoryPermissionStaff'   => 'Y',
    'categoryPermissionStudent' => 'N',
    'categoryPermissionParent'  => 'N',
    'categoryPermissionOther'   => 'N',
];

$actionRows[] = [
    'name'                      => 'Registrar Reports',
    'precedence'                => '3',
    'category'                  => 'Registrar',
    'description'               => 'Filter and sort student records across term, program, mode of instruction, gender, level, and grade ranges.',
    'URLList'                   => 'query_engine.php',
    'entryURL'                  => 'query_engine.php',
    'defaultPermissionAdmin'    => 'Y',
    'defaultPermissionTeacher'  => 'N',
    'defaultPermissionStudent'  => 'N',
    'defaultPermissionParent'   => 'N',
    'defaultPermissionSupport'  => 'N',
    'categoryPermissionStaff'   => 'Y',
    'categoryPermissionStudent' => 'N',
    'categoryPermissionParent'  => 'N',
    'categoryPermissionOther'   => 'N',
];

$actionRows[] = [
    'name'                      => 'Course Details',
    'precedence'                => '5',
    'category'                  => 'Registrar',
    'description'               => 'Set course level and concentration for a school year.',
    'URLList'                   => 'course_detail_manage.php, course_detail_manageProcess.php',
    'entryURL'                  => 'course_detail_manage.php',
    'defaultPermissionAdmin'    => 'Y',
    'defaultPermissionTeacher'  => 'N',
    'defaultPermissionStudent'  => 'N',
    'defaultPermissionParent'   => 'N',
    'defaultPermissionSupport'  => 'N',
    'categoryPermissionStaff'   => 'Y',
    'categoryPermissionStudent' => 'N',
    'categoryPermissionParent'  => 'N',
    'categoryPermissionOther'   => 'N',
];

$actionRows[] = [
    'name'                      => 'Student Mode',
    'precedence'                => '6',
    'category'                  => 'Registrar',
    'description'               => 'Set each student\'s mode of instruction for each term.',
    'URLList'                   => 'student_mode_manage.php, student_mode_manageProcess.php',
    'entryURL'                  => 'student_mode_manage.php',
    'defaultPermissionAdmin'    => 'Y',
    'defaultPermissionTeacher'  => 'N',
    'defaultPermissionStudent'  => 'N',
    'defaultPermissionParent'   => 'N',
    'defaultPermissionSupport'  => 'N',
    'categoryPermissionStaff'   => 'Y',
    'categoryPermissionStudent' => 'N',
    'categoryPermissionParent'  => 'N',
    'categoryPermissionOther'   => 'N',
];

$actionRows[] = [
    'name'                      => 'Transcript Template',
    'precedence'                => '4',
    'category'                  => 'Registrar',
    'description'               => 'Upload and configure the official PDF transcript template.',
    'URLList'                   => 'template_manage.php, template_manageProcess.php',
    'entryURL'                  => 'template_manage.php',
    'defaultPermissionAdmin'    => 'Y',
    'defaultPermissionTeacher'  => 'N',
    'defaultPermissionStudent'  => 'N',
    'defaultPermissionParent'   => 'N',
    'defaultPermissionSupport'  => 'N',
    'categoryPermissionStaff'   => 'Y',
    'categoryPermissionStudent' => 'N',
    'categoryPermissionParent'  => 'N',
    'categoryPermissionOther'   => 'N',
];
