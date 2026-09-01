<?php
// Manifest file for Transcripts module

$name        = 'Transcripts';
$description = 'Automated transcript generation and advanced registrar reporting.';
$entryURL    = 'transcripts_view.php';
$type        = 'Additional';
$version     = '1.0.3';
$author      = 'SPOTS Development Team';
$url         = 'https://spots.edu';
$category    = 'Assess';

$moduleTables = [
    "CREATE TABLE IF NOT EXISTS `gibbonStudentProgramHistory` (
        `gibbonStudentProgramHistoryID` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
        `gibbonPersonID` INT(10) UNSIGNED NOT NULL,
        `programType` ENUM('MTS', 'BTh', 'Certificate', 'Iconography', 'Iconology', 'Gap-Year', 'Non-Degree') NOT NULL,
        `concentration` ENUM('Biblical', 'Professional', 'General', 'Certificate', 'Masters') DEFAULT 'General',
        `studentLevel` ENUM('Freshman', 'Sophomore', 'Junior', 'Senior', 'M1', 'M2', 'M3') DEFAULT NULL,
        `startDate` DATE NOT NULL,
        `switchDate` DATE DEFAULT NULL,
        `graduationDate` DATE DEFAULT NULL,
        `status` ENUM('Active', 'Switched', 'Graduated', 'Withdrawn', 'On Leave') NOT NULL DEFAULT 'Active',
        `notes` TEXT DEFAULT NULL,
        PRIMARY KEY (`gibbonStudentProgramHistoryID`),
        KEY `gibbonPersonID` (`gibbonPersonID`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "CREATE TABLE IF NOT EXISTS `gibbonTranscriptsCourseProgram` (
        `gibbonTranscriptsCourseProgramID` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
        `courseCode` VARCHAR(60) NOT NULL,
        `programType` ENUM('MTS', 'BTh', 'Certificate', 'Iconography', 'Iconology', 'Gap-Year', 'Non-Degree') NOT NULL,
        PRIMARY KEY (`gibbonTranscriptsCourseProgramID`),
        UNIQUE KEY `courseProgram` (`courseCode`, `programType`),
        KEY `programType` (`programType`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
];

$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Transcripts', 'customAssetPath', 'Custom Asset Path', 'Relative folder for transcript PDF assets.', '/uploads/transcripts');";
$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Transcripts', 'page1BackgroundPath', 'Page 1 Background PDF', 'Vector PDF background for page one (logo and full header artwork).', '');";
$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Transcripts', 'page2BackgroundPath', 'Page 2 Background PDF', 'Vector PDF background for continuation pages.', '');";
$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Transcripts', 'registrarSignaturePath', 'Registrar Signature', 'Relative path to the registrar signature image.', '');";
$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Transcripts', 'registrarGibbonPersonID', 'Registrar User', 'Gibbon user who may generate official signed transcripts.', '');";

$actionRows[] = [
    'name'                      => 'Generate Transcripts_all',
    'precedence'                => '3',
    'category'                  => 'Transcripts',
    'description'               => 'View and export transcripts for any student.',
    'URLList'                   => 'transcripts_view.php, transcript_print.php, transcripts_edit.php, transcripts_editProcess.php',
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
    'name'                      => 'Generate Transcripts_myStudents',
    'precedence'                => '2',
    'category'                  => 'Transcripts',
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
    'name'                      => 'Generate Transcripts_myTranscript',
    'precedence'                => '1',
    'category'                  => 'Transcripts',
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
    'name'                      => 'Manage Student Programs',
    'precedence'                => '2',
    'category'                  => 'Registrar Admin',
    'description'               => 'Manage program start, switch, and graduation dates and student concentrations.',
    'URLList'                   => 'program_manage.php, program_manageProcess.php, program_manage_add.php, program_manage_edit.php, program_manage_editProcess.php',
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
    'name'                      => 'Advanced Registrar Reports',
    'precedence'                => '3',
    'category'                  => 'Reports',
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
    'name'                      => 'Manage Course Programs',
    'precedence'                => '5',
    'category'                  => 'Registrar Admin',
    'description'               => 'Assign courses to programs so transcripts include the correct grades.',
    'URLList'                   => 'course_program.php, course_program_add.php, course_program_edit.php, course_program_editProcess.php',
    'entryURL'                  => 'course_program.php',
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
    'name'                      => 'Manage Transcript Template',
    'precedence'                => '4',
    'category'                  => 'Registrar Admin',
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
