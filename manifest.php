<?php
// Manifest file for Transcripts module

$name        = 'Transcripts';
$description = 'Automated transcript generation, term alias mapping, and advanced registrar reporting for SPOTS.';
$entryURL    = 'transcripts_view.php';
$type        = 'Additional';
$version     = '1.0.0';
$author      = 'SPOTS Development Team';
$url         = 'https://spots.edu';
$category    = 'Assess';

// Module Tables Creation SQL (executed when installing or enabling the module in Gibbon)
$moduleTables = [
    "CREATE TABLE IF NOT EXISTS `gibbonStudentProgramHistory` (
        `gibbonStudentProgramHistoryID` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
        `gibbonPersonID` INT(10) UNSIGNED NOT NULL,
        `programType` ENUM('MTS', 'BTh', 'Iconography', 'Iconology', 'Gap-Year', 'Non-Degree') NOT NULL,
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

    "CREATE TABLE IF NOT EXISTS `gibbonTermAlias` (
        `gibbonTermAliasID` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
        `gibbonSchoolYearTermID` INT(10) UNSIGNED NOT NULL,
        `ecclesiasticalName` VARCHAR(50) NOT NULL COMMENT 'e.g. Pascha, Nativity, Pentecost',
        `secularAlias` VARCHAR(50) NOT NULL COMMENT 'e.g. Spring Term, Fall Term, Summer Term',
        `notes` VARCHAR(255) DEFAULT NULL,
        PRIMARY KEY (`gibbonTermAliasID`),
        UNIQUE KEY `gibbonSchoolYearTermID` (`gibbonSchoolYearTermID`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
];

// Action 1: Transcript Generation / View
$actionRows[] = [
    'name'                      => 'Generate Transcripts',
    'precedence'                => '1',
    'category'                  => 'Transcripts',
    'description'               => 'View and export formal student transcripts.',
    'URLList'                   => 'transcripts_view.php, transcript_print.php',
    'entryURL'                  => 'transcripts_view.php',
    'defaultPermissionAdmin'    => 'Y',
    'defaultPermissionTeacher'  => 'Y',
    'defaultPermissionStudent'  => 'Y',
    'defaultPermissionParent'   => 'N',
    'defaultPermissionSupport'  => 'Y',
    'categoryPermissionStaff'   => 'Y',
    'categoryPermissionStudent' => 'Y',
    'categoryPermissionParent'  => 'N',
    'categoryPermissionOther'   => 'N',
];

// Action 2: Student Program & Dates Management
$actionRows[] = [
    'name'                      => 'Manage Student Programs & Dates',
    'precedence'                => '2',
    'category'                  => 'Registrar Admin',
    'description'               => 'Manage program start, switch, and graduation dates and student concentrations.',
    'URLList'                   => 'program_manage.php, program_edit.php, program_process.php',
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

// Action 3: Advanced Registrar Query Engine
$actionRows[] = [
    'name'                      => 'Advanced Registrar Reports',
    'precedence'                => '3',
    'category'                  => 'Reports',
    'description'               => 'Filter and sort student records across term, program, mode of instruction, gender, level, and grade ranges.',
    'URLList'                   => 'query_engine.php, query_export.php',
    'entryURL'                  => 'query_engine.php',
    'defaultPermissionAdmin'    => 'Y',
    'defaultPermissionTeacher'  => 'Y',
    'defaultPermissionStudent'  => 'N',
    'defaultPermissionParent'   => 'N',
    'defaultPermissionSupport'  => 'Y',
    'categoryPermissionStaff'   => 'Y',
    'categoryPermissionStudent' => 'N',
    'categoryPermissionParent'  => 'N',
    'categoryPermissionOther'   => 'N',
];

// Action 4: Term Aliases Management
$actionRows[] = [
    'name'                      => 'Term Name Aliases',
    'precedence'                => '4',
    'category'                  => 'Settings',
    'description'               => 'Map ecclesiastical term names to secular display aliases for official transcripts.',
    'URLList'                   => 'term_aliases.php, term_aliases_process.php',
    'entryURL'                  => 'term_aliases.php',
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
