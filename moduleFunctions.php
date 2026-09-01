<?php

function checkAndMigrateTranscriptsSettings($pdo)
{
    foreach (getTranscriptsSettingsDefinitions() as [$scope, $name, $nameDisplay, $description, $value]) {
        try {
            $existing = $pdo->selectOne(
                'SELECT gibbonSettingID FROM gibbonSetting WHERE scope=:scope AND name=:name',
                ['scope' => $scope, 'name' => $name]
            );

            if (empty($existing)) {
                $pdo->insert(
                    'INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value) VALUES (:scope, :name, :nameDisplay, :description, :value)',
                    [
                        'scope' => $scope,
                        'name' => $name,
                        'nameDisplay' => $nameDisplay,
                        'description' => $description,
                        'value' => $value,
                    ]
                );
            }
        } catch (Exception $e) {
            // Settings table may be unavailable during install.
        }
    }
}

function getTranscriptsSettingsDefinitions(): array
{
    return [
        ['Transcripts', 'customAssetPath', 'Custom Asset Path', 'Relative folder for transcript PDF assets.', '/uploads/transcripts'],
        ['Transcripts', 'page1BackgroundPath', 'Page 1 Background PDF', 'Vector PDF background for page one (logo and full header artwork).', ''],
        ['Transcripts', 'page2BackgroundPath', 'Page 2 Background PDF', 'Vector PDF background for continuation pages.', ''],
        ['Transcripts', 'registrarSignaturePath', 'Registrar Signature', 'Relative path to the registrar signature image.', ''],
        ['Transcripts', 'registrarGibbonPersonID', 'Registrar User', 'Gibbon user who may generate official signed transcripts.', ''],
    ];
}

function upsertTranscriptsSetting($pdo, string $name, string $value): void
{
    $definitions = [];
    foreach (getTranscriptsSettingsDefinitions() as [$scope, $settingName, $nameDisplay, $description, $defaultValue]) {
        $definitions[$settingName] = [$scope, $nameDisplay, $description, $defaultValue];
    }

    if (!isset($definitions[$name])) {
        return;
    }

    [$scope, $nameDisplay, $description] = $definitions[$name];

    $existing = $pdo->selectOne(
        'SELECT gibbonSettingID FROM gibbonSetting WHERE scope=:scope AND name=:name',
        ['scope' => $scope, 'name' => $name]
    );

    if (empty($existing)) {
        $pdo->insert(
            'INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value) VALUES (:scope, :name, :nameDisplay, :description, :value)',
            [
                'scope' => $scope,
                'name' => $name,
                'nameDisplay' => $nameDisplay,
                'description' => $description,
                'value' => $value,
            ]
        );

        return;
    }

    $pdo->update(
        'UPDATE gibbonSetting SET value=:value WHERE scope=:scope AND name=:name',
        ['scope' => $scope, 'name' => $name, 'value' => $value]
    );
}

function checkAndMigrateTranscriptsSchema($pdo)
{
    $columns = [
        'modeOfInstruction' => "ALTER TABLE `gibbonCourse` ADD COLUMN `modeOfInstruction` ENUM('In-person', 'Remote') NOT NULL DEFAULT 'In-person'",
        'courseLevel' => "ALTER TABLE `gibbonCourse` ADD COLUMN `courseLevel` ENUM('BTh', 'MTS', 'Certificate', 'Non-Degree') NOT NULL DEFAULT 'BTh'",
        'credits' => "ALTER TABLE `gibbonCourse` ADD COLUMN `credits` DECIMAL(4,2) NOT NULL DEFAULT 0.00",
    ];

    foreach ($columns as $column => $sql) {
        try {
            $check = $pdo->selectOne("SHOW COLUMNS FROM `gibbonCourse` LIKE :column", ['column' => $column]);
            if (empty($check)) {
                $pdo->statement($sql);
            }
        } catch (Exception $e) {
            // Column may already exist or table may be unavailable during install.
        }
    }

    try {
        $pdo->statement("ALTER TABLE `gibbonStudentProgramHistory` MODIFY COLUMN `programType` ENUM('MTS', 'BTh', 'Certificate', 'Iconography', 'Iconology', 'Gap-Year', 'Non-Degree') NOT NULL");
    } catch (Exception $e) {
        // Table may be unavailable, or the ENUM already includes Certificate.
    }

    try {
        $pdo->statement("CREATE TABLE IF NOT EXISTS `gibbonTranscriptsCourseProgram` (
            `gibbonTranscriptsCourseProgramID` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `courseCode` VARCHAR(60) NOT NULL,
            `programType` ENUM('MTS', 'BTh', 'Certificate', 'Iconography', 'Iconology', 'Gap-Year', 'Non-Degree') NOT NULL,
            PRIMARY KEY (`gibbonTranscriptsCourseProgramID`),
            UNIQUE KEY `courseProgram` (`courseCode`, `programType`),
            KEY `programType` (`programType`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Exception $e) {
        // Table may already exist or be unavailable during install.
    }
}

function getTranscriptsProgramTypes(): array
{
    return [
        'MTS' => 'MTS',
        'BTh' => 'BTh',
        'Certificate' => 'Certificate',
        'Iconography' => 'Iconography',
        'Iconology' => 'Iconology',
        'Gap-Year' => 'Gap-Year',
        'Non-Degree' => 'Non-Degree',
    ];
}

function getTranscriptsConcentrations(): array
{
    return [
        'Biblical' => 'Biblical',
        'Professional' => 'Professional',
        'General' => 'General',
        'Certificate' => 'Certificate',
        'Masters' => 'Masters',
    ];
}

function getTranscriptsStudentLevels(): array
{
    return [
        'Freshman' => 'Freshman',
        'Sophomore' => 'Sophomore',
        'Junior' => 'Junior',
        'Senior' => 'Senior',
        'M1' => 'M1',
        'M2' => 'M2',
        'M3' => 'M3',
    ];
}

function getTranscriptsProgramStatuses(): array
{
    return [
        'Active' => 'Active',
        'Switched' => 'Switched',
        'Graduated' => 'Graduated',
        'Withdrawn' => 'Withdrawn',
        'On Leave' => 'On Leave',
    ];
}

function getTranscriptsInstructionModes(): array
{
    return [
        'In-person' => 'In-person',
        'Remote' => 'Remote',
    ];
}

function renderGpaBadge($gpa)
{
    $class = 'success';
    if ($gpa < 2.0) {
        $class = 'error';
    } elseif ($gpa < 3.0) {
        $class = 'warning';
    }

    return '<span class="tag '.$class.'">'.number_format($gpa, 2).'</span>';
}

function canGenerateOfficialTranscript($guid, $connection2, $settingGateway = null): bool
{
    global $session, $container;

    if ($settingGateway === null) {
        $settingGateway = $container->get(\Gibbon\Domain\System\SettingGateway::class);
    }

    $registrarPersonID = (int)$settingGateway->getSettingByScope('Transcripts', 'registrarGibbonPersonID');

    if ($registrarPersonID > 0) {
        return (int)$session->get('gibbonPersonID') === $registrarPersonID;
    }

    return isActionAccessible($guid, $connection2, '/modules/Transcripts/template_manage.php');
}

function getTranscriptViewAction($guid, $connection2): ?string
{
    $action = getHighestGroupedAction($guid, '/modules/Transcripts/transcripts_view.php', $connection2);

    return !empty($action) ? $action : null;
}

function getTeacherStudentOptions($pdo, int $gibbonSchoolYearID, int $gibbonPersonIDTeacher): array
{
    $sql = "SELECT DISTINCT gibbonPerson.gibbonPersonID, gibbonPerson.preferredName, gibbonPerson.surname, gibbonPerson.username
            FROM gibbonCourseClassPerson AS teacherClass
            JOIN gibbonCourseClass ON (teacherClass.gibbonCourseClassID=gibbonCourseClass.gibbonCourseClassID)
            JOIN gibbonCourse ON (gibbonCourseClass.gibbonCourseID=gibbonCourse.gibbonCourseID)
            JOIN gibbonCourseClassPerson AS studentClass ON (studentClass.gibbonCourseClassID=gibbonCourseClass.gibbonCourseClassID)
            JOIN gibbonPerson ON (studentClass.gibbonPersonID=gibbonPerson.gibbonPersonID)
            WHERE teacherClass.gibbonPersonID=:gibbonPersonIDTeacher
            AND teacherClass.role='Teacher'
            AND studentClass.role='Student'
            AND studentClass.reportable='Y'
            AND gibbonCourse.gibbonSchoolYearID=:gibbonSchoolYearID
            AND gibbonPerson.status='Full'
            AND (gibbonPerson.dateStart IS NULL OR gibbonPerson.dateStart<=:date)
            AND (gibbonPerson.dateEnd IS NULL OR gibbonPerson.dateEnd>=:date)
            ORDER BY gibbonPerson.surname, gibbonPerson.preferredName";

    $results = $pdo->select($sql, [
        'gibbonPersonIDTeacher' => $gibbonPersonIDTeacher,
        'gibbonSchoolYearID' => $gibbonSchoolYearID,
        'date' => date('Y-m-d'),
    ]);

    $options = [];
    foreach ($results as $row) {
        $options[$row['gibbonPersonID']] = \Gibbon\Services\Format::name('', $row['preferredName'], $row['surname'], 'Student', true).' ('.$row['username'].')';
    }

    return $options;
}

function canViewStudentTranscript($pdo, string $highestAction, int $gibbonPersonIDViewer, int $gibbonPersonIDStudent, int $gibbonSchoolYearID): bool
{
    if ($gibbonPersonIDStudent <= 0) {
        return false;
    }

    if ($highestAction === 'Generate Transcripts_myTranscript') {
        return $gibbonPersonIDViewer === $gibbonPersonIDStudent;
    }

    if ($highestAction === 'Generate Transcripts_myStudents') {
        $students = getTeacherStudentOptions($pdo, $gibbonSchoolYearID, $gibbonPersonIDViewer);

        return isset($students[$gibbonPersonIDStudent]);
    }

    if ($highestAction === 'Generate Transcripts_all') {
        $sql = "SELECT gibbonPerson.gibbonPersonID
                FROM gibbonPerson
                JOIN gibbonStudentEnrolment ON (gibbonPerson.gibbonPersonID=gibbonStudentEnrolment.gibbonPersonID)
                WHERE gibbonPerson.gibbonPersonID=:gibbonPersonID
                AND gibbonStudentEnrolment.gibbonSchoolYearID=:gibbonSchoolYearID
                AND gibbonPerson.status='Full'
                AND (gibbonPerson.dateStart IS NULL OR gibbonPerson.dateStart<=:date)
                AND (gibbonPerson.dateEnd IS NULL OR gibbonPerson.dateEnd>=:date)";

        $row = $pdo->selectOne($sql, [
            'gibbonPersonID' => $gibbonPersonIDStudent,
            'gibbonSchoolYearID' => $gibbonSchoolYearID,
            'date' => date('Y-m-d'),
        ]);

        return !empty($row);
    }

    return false;
}

function getTranscriptsProgramCourseLevel(array $program): string
{
    if (($program['concentration'] ?? '') === 'Certificate') {
        return 'Certificate';
    }

    $map = [
        'MTS' => 'MTS',
        'BTh' => 'BTh',
        'Certificate' => 'Certificate',
        'Iconography' => 'Non-Degree',
        'Iconology' => 'Non-Degree',
        'Gap-Year' => 'Non-Degree',
        'Non-Degree' => 'Non-Degree',
    ];

    return $map[$program['programType'] ?? ''] ?? 'BTh';
}

function formatTranscriptsProgramLabel(array $program): string
{
    $parts = array_filter([
        $program['programType'] ?? '',
        $program['concentration'] ?? '',
        $program['status'] ?? '',
    ]);

    $dates = '';
    if (!empty($program['startDate'])) {
        $dates = \Gibbon\Services\Format::date($program['startDate']);
        if (!empty($program['graduationDate'])) {
            $dates .= ' – '.\Gibbon\Services\Format::date($program['graduationDate']);
        } elseif (!empty($program['switchDate'])) {
            $dates .= ' – '.\Gibbon\Services\Format::date($program['switchDate']);
        }
    }

    $label = implode(' · ', $parts);

    return $dates !== '' ? $label.' ('.$dates.')' : $label;
}

function resolveTranscriptsProgram(array $programs, $gibbonStudentProgramHistoryID = 0): ?array
{
    if (empty($programs)) {
        return null;
    }

    $selectedID = (int)$gibbonStudentProgramHistoryID;
    if ($selectedID > 0) {
        foreach ($programs as $program) {
            if ((int)($program['gibbonStudentProgramHistoryID'] ?? 0) === $selectedID) {
                return $program;
            }
        }
    }

    foreach ($programs as $program) {
        if (($program['status'] ?? '') === 'Active') {
            return $program;
        }
    }

    return $programs[array_key_last($programs)];
}

function getTranscriptsProgramOptions(array $programs): array
{
    $options = [];
    foreach ($programs as $program) {
        $id = (int)($program['gibbonStudentProgramHistoryID'] ?? 0);
        if ($id > 0) {
            $options[$id] = formatTranscriptsProgramLabel($program);
        }
    }

    return $options;
}
