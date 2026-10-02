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
        $pdo->statement("CREATE TABLE IF NOT EXISTS `gibbonTranscriptProgram` (
            `gibbonTranscriptProgramID` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(30) NOT NULL,
            `sequenceNumber` INT NOT NULL DEFAULT 0,
            PRIMARY KEY (`gibbonTranscriptProgramID`),
            UNIQUE KEY `name` (`name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $existingPrograms = (int) $pdo->selectOne('SELECT COUNT(*) FROM gibbonTranscriptProgram');
        if ($existingPrograms === 0) {
            $sequence = 0;
            foreach (array_keys(getTranscriptsProgramTypeDefaults()) as $programName) {
                $sequence++;
                $pdo->insert(
                    'INSERT INTO gibbonTranscriptProgram (name, sequenceNumber) VALUES (:name, :sequenceNumber)',
                    ['name' => $programName, 'sequenceNumber' => $sequence]
                );
            }
        }
        $pdo->statement("ALTER TABLE `gibbonStudentProgramHistory` MODIFY COLUMN `programType` VARCHAR(30) NOT NULL");
    } catch (Exception $e) {
        // Table may be unavailable during install.
    }

    try {
        $pdo->statement("CREATE TABLE IF NOT EXISTS `gibbonTermAlias` (
            `gibbonTermAliasID` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `gibbonSchoolYearTermID` INT(10) UNSIGNED NOT NULL,
            `ecclesiasticalName` VARCHAR(50) NOT NULL,
            `secularAlias` VARCHAR(50) NOT NULL,
            `notes` VARCHAR(255) DEFAULT NULL,
            PRIMARY KEY (`gibbonTermAliasID`),
            UNIQUE KEY `gibbonSchoolYearTermID` (`gibbonSchoolYearTermID`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Exception $e) {
        // Table may already exist or be unavailable during install.
    }
}

/**
 * The course catalog (external code, credits) belongs to Courses and Classes; its classes are
 * not on the core autoloader when a Transcripts page is running.
 */
function registerCoursesAndClassesAutoloader(string $absolutePath): void
{
    static $registered = false;
    if ($registered) {
        return;
    }
    $registered = true;

    $baseDir = rtrim($absolutePath, '/').'/modules/Courses and Classes/src/';
    spl_autoload_register(function ($class) use ($baseDir) {
        $prefix = 'Gibbon\\Module\\CoursesAndClasses\\';
        if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
            return;
        }

        $file = $baseDir.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';
        if (file_exists($file)) {
            require_once $file;
        }
    });
}

function getTranscriptsProgramTypeDefaults(): array
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

/**
 * The program names shared by Transcripts and Tuition Billing. Reads gibbonTranscriptProgram,
 * which Manage Student Programs edits. The built-in names are used only before that table exists.
 */
function getTranscriptsProgramTypes($pdo = null): array
{
    if ($pdo !== null) {
        try {
            $rows = $pdo->select('SELECT name FROM gibbonTranscriptProgram ORDER BY sequenceNumber, name')->fetchAll();
            if (!empty($rows)) {
                $names = array_column($rows, 'name');

                return array_combine($names, $names);
            }
        } catch (Exception $e) {
            // Table is created by the module update.
        }
    }

    return getTranscriptsProgramTypeDefaults();
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

/**
 * Every person who has ever been enrolled as a student or has a grade, in any year and with any
 * status, grouped so registrars can find alumni and leavers as easily as current students.
 */
function getTranscriptStudentOptions($pdo): array
{
    $sql = "SELECT gibbonPerson.gibbonPersonID, gibbonPerson.surname, gibbonPerson.preferredName, gibbonPerson.username, gibbonPerson.status,
                (SELECT gibbonSchoolYear.name
                    FROM gibbonStudentEnrolment
                    JOIN gibbonSchoolYear ON (gibbonSchoolYear.gibbonSchoolYearID=gibbonStudentEnrolment.gibbonSchoolYearID)
                    WHERE gibbonStudentEnrolment.gibbonPersonID=gibbonPerson.gibbonPersonID
                    ORDER BY gibbonSchoolYear.sequenceNumber DESC
                    LIMIT 1) AS lastSchoolYear
            FROM gibbonPerson
            WHERE EXISTS (SELECT 1 FROM gibbonStudentEnrolment WHERE gibbonStudentEnrolment.gibbonPersonID=gibbonPerson.gibbonPersonID)
            OR EXISTS (SELECT 1 FROM gibbonReportingValue WHERE gibbonReportingValue.gibbonPersonIDStudent=gibbonPerson.gibbonPersonID)
            ORDER BY gibbonPerson.surname, gibbonPerson.preferredName";

    $groups = [
        'Full' => __('Current Students'),
        'Left' => __('Left / Alumni'),
        'Expected' => __('Expected'),
        'Pending' => __('Pending'),
    ];

    $options = [];
    foreach ($pdo->select($sql)->fetchAll() as $row) {
        $group = $groups[$row['status']] ?? __('Other');
        $label = \Gibbon\Services\Format::name('', $row['preferredName'], $row['surname'], 'Student', true);
        $label .= !empty($row['username']) ? ' ('.$row['username'].')' : '';
        $label .= !empty($row['lastSchoolYear']) ? ' – '.$row['lastSchoolYear'] : '';
        $options[$group][$row['gibbonPersonID']] = $label;
    }

    $ordered = [];
    foreach (array_merge(array_values($groups), [__('Other')]) as $group) {
        if (!empty($options[$group])) {
            $ordered[$group] = $options[$group];
        }
    }

    return $ordered;
}

/**
 * Returns null when the viewer may see the student's transcript, otherwise the reason they cannot.
 */
function getTranscriptAccessDenialReason($pdo, string $highestAction, int $gibbonPersonIDViewer, int $gibbonPersonIDStudent, int $gibbonSchoolYearID): ?string
{
    if ($gibbonPersonIDStudent <= 0) {
        return __('No student was selected.');
    }

    if ($highestAction === 'Generate Transcripts_myTranscript') {
        return $gibbonPersonIDViewer === $gibbonPersonIDStudent
            ? null
            : __('You can only view your own transcript.');
    }

    if ($highestAction === 'Generate Transcripts_myStudents') {
        $students = getTeacherStudentOptions($pdo, $gibbonSchoolYearID, $gibbonPersonIDViewer);

        return isset($students[$gibbonPersonIDStudent])
            ? null
            : __('You can only view transcripts for current students in the classes you teach this school year.');
    }

    if ($highestAction === 'Generate Transcripts_all') {
        $person = $pdo->selectOne(
            "SELECT gibbonPersonID,
                EXISTS (SELECT 1 FROM gibbonStudentEnrolment WHERE gibbonStudentEnrolment.gibbonPersonID=gibbonPerson.gibbonPersonID)
                OR EXISTS (SELECT 1 FROM gibbonReportingValue WHERE gibbonReportingValue.gibbonPersonIDStudent=gibbonPerson.gibbonPersonID) AS isStudent
             FROM gibbonPerson
             WHERE gibbonPersonID=:gibbonPersonID",
            ['gibbonPersonID' => $gibbonPersonIDStudent]
        );

        if (empty($person)) {
            return __('The selected person could not be found.');
        }

        return !empty($person['isStudent'])
            ? null
            : __('This person has never been enrolled as a student and has no grades.');
    }

    return __('You do not have access to this action.');
}

function canViewStudentTranscript($pdo, string $highestAction, int $gibbonPersonIDViewer, int $gibbonPersonIDStudent, int $gibbonSchoolYearID): bool
{
    return getTranscriptAccessDenialReason($pdo, $highestAction, $gibbonPersonIDViewer, $gibbonPersonIDStudent, $gibbonSchoolYearID) === null;
}

/**
 * Grade options for an editable cell: one group per reporting cycle the class can be graded in,
 * with values encoded as "criteriaID:scaleGradeID".
 */
function buildTranscriptGradeChoices($transcriptGateway, array $criteriaRows, array &$scaleCache): array
{
    $choices = [];
    foreach ($criteriaRows as $criteria) {
        $scaleID = (int)($criteria['gibbonScaleID'] ?? 0);
        if (!isset($scaleCache[$scaleID])) {
            $scaleCache[$scaleID] = $transcriptGateway->getGradeScaleOptionsByScaleID($scaleID);
        }

        $choices[] = [
            'gibbonReportingCriteriaID' => (int)$criteria['gibbonReportingCriteriaID'],
            'label' => $criteria['termName'] ?: ($criteria['cycleName'] ?? ''),
            'grades' => $scaleCache[$scaleID],
        ];
    }

    return $choices;
}

function renderTranscriptLastChanged(array $row): string
{
    if (empty($row['timestampModified']) || empty($row['modifiedSurname'])) {
        return '';
    }

    $name = \Gibbon\Services\Format::name($row['modifiedTitle'] ?? '', $row['modifiedPreferredName'] ?? '', $row['modifiedSurname'], 'Staff', false, true);

    return '<div class="text-xxs text-gray-600 mt-1">'.sprintf(__('Last changed by %1$s on %2$s'), htmlspecialchars($name), \Gibbon\Services\Format::dateTime($row['timestampModified'])).'</div>';
}

/**
 * In-place grade select. Graded rows offer their own criterion only; ungraded rows offer every
 * cycle the class can be graded in and reload the page once saved.
 */
function renderTranscriptGradeCell(array $row, array $choices, array $context, bool $reloadOnSave = false): string
{
    $classID = (int)($row['gibbonCourseClassID'] ?? 0);
    $criteriaID = (int)($row['gibbonReportingCriteriaID'] ?? 0);
    $selectedGradeID = (int)($row['gibbonScaleGradeID'] ?? 0);
    $currentLabel = trim((string)($row['letterGrade'] ?? ''));

    if (empty($choices)) {
        return '<div class="transcriptGradeCell">'.htmlspecialchars($currentLabel !== '' ? $currentLabel : '-').renderTranscriptLastChanged($row).'</div>';
    }

    $vals = [
        'action' => 'saveGrade',
        'csrftoken' => $context['csrftoken'],
        'gibbonPersonID' => $context['gibbonPersonID'],
        'gibbonCourseClassID' => $classID,
        'reload' => $reloadOnSave ? 'Y' : 'N',
    ];

    $html = '<div class="transcriptGradeCell">';
    $html .= '<select name="grade" class="w-full max-w-xs" aria-label="'.__('Grade').'"'
        .' hx-post="'.htmlspecialchars($context['ajaxURL']).'" hx-trigger="change" hx-target="closest .transcriptGradeCell" hx-swap="outerHTML"'
        .' hx-vals="'.htmlspecialchars(json_encode($vals), ENT_QUOTES).'">';

    if ($criteriaID > 0) {
        if ($selectedGradeID <= 0) {
            $html .= '<option value="" selected disabled>'.htmlspecialchars($currentLabel !== '' ? $currentLabel : '-').'</option>';
        }
        $html .= '<option value="'.$criteriaID.':">'.__('Clear grade').'</option>';
    } else {
        $html .= '<option value="" selected disabled>'.__('Select grade').'</option>';
    }

    foreach ($choices as $choice) {
        $useGroup = $criteriaID <= 0 && count($choices) > 1;
        if ($useGroup) {
            $html .= '<optgroup label="'.htmlspecialchars($choice['label']).'">';
        }
        foreach ($choice['grades'] as $gradeID => $label) {
            $selected = $choice['gibbonReportingCriteriaID'] === $criteriaID && (int)$gradeID === $selectedGradeID;
            $html .= '<option value="'.$choice['gibbonReportingCriteriaID'].':'.(int)$gradeID.'"'.($selected ? ' selected' : '').'>'.htmlspecialchars($label).'</option>';
        }
        if ($useGroup) {
            $html .= '</optgroup>';
        }
    }

    $html .= '</select>';
    $html .= renderTranscriptLastChanged($row);
    $html .= '</div>';

    return $html;
}

/**
 * In-place credits input; credits belong to the Courses and Classes catalog.
 */
function renderTranscriptCatalogInput(array $row, string $field, array $context): string
{
    $courseCode = (string)($row['courseCode'] ?? '');
    $vals = [
        'action' => 'saveCatalog',
        'csrftoken' => $context['csrftoken'],
        'gibbonPersonID' => $context['gibbonPersonID'],
        'courseCode' => $courseCode,
        'field' => $field,
    ];

    $warning = sprintf(__('This changes course %1$s for every student and every year, including tuition billing.'), $courseCode);
    $common = ' name="value" hx-post="'.htmlspecialchars($context['ajaxURL']).'" hx-trigger="change" hx-swap="none"'
        .' hx-vals="'.htmlspecialchars(json_encode($vals), ENT_QUOTES).'"'
        .' data-catalog-confirm="'.htmlspecialchars($warning).'" data-course-code="'.htmlspecialchars($courseCode).'"';

    $value = number_format((float)($row['credits'] ?? 0), 2, '.', '');

    return '<input type="number" step="0.01" min="0" max="99.99" class="w-20 text-right" aria-label="'.__('Credits').'" value="'.$value.'"'.$common.'>';
}

function renderTranscriptSummary(array $transcriptData, ?array $selectedProgram, string $printUrl, bool $isOfficial, bool $outOfBand = false): string
{
    $html = '<div id="transcriptSummary" class="linkTop"'.($outOfBand ? ' hx-swap-oob="true"' : '').'>';
    if (!empty($selectedProgram)) {
        $html .= '<strong>'.__('Program').':</strong> '.htmlspecialchars(formatTranscriptsProgramLabel($selectedProgram)).' | ';
    }
    $html .= '<strong>'.__('Cumulative GPA').':</strong> '.renderGpaBadge($transcriptData['cumulativeGPA']);
    $html .= ' | <strong>'.__('Total Credits Earned').':</strong> '.number_format((float)$transcriptData['totalCredits'], 2);
    $html .= ' | <a href="'.htmlspecialchars($printUrl).'" target="_blank">'.($isOfficial ? __('Official PDF') : __('Unofficial PDF')).'</a>';
    $html .= '</div>';

    return $html;
}

/**
 * Confirms the first catalog edit per course per browser session, and surfaces save errors.
 */
function renderTranscriptInlineEditScript(): string
{
    return <<<'HTML'
<script>
(function () {
    if (window.transcriptsInlineEdit) return;
    window.transcriptsInlineEdit = true;

    document.body.addEventListener('htmx:confirm', function (evt) {
        var el = evt.detail.elt;
        if (!el || !el.dataset || !el.dataset.catalogConfirm) return;

        var key = 'transcriptsCatalogConfirmed:' + el.dataset.courseCode;
        try {
            if (window.sessionStorage.getItem(key)) return;
        } catch (e) {}

        evt.preventDefault();
        if (window.confirm(el.dataset.catalogConfirm)) {
            try { window.sessionStorage.setItem(key, '1'); } catch (e) {}
            evt.detail.issueRequest(true);
        } else {
            el.value = el.defaultValue;
        }
    });

    document.body.addEventListener('htmx:responseError', function (evt) {
        var el = evt.detail.elt;
        if (!el || !el.closest || !el.closest('.transcriptsEditable')) return;
        window.alert(evt.detail.xhr.responseText || 'The change could not be saved.');
        if (el.dataset && el.dataset.catalogConfirm) {
            el.value = el.defaultValue;
        }
    });
})();
</script>
HTML;
}

function formatTranscriptsProgramLabel(array $program): string
{
    $parts = array_filter([
        $program['programType'] ?? '',
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
