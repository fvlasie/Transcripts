<?php

use Gibbon\Forms\Form;
use Gibbon\Services\Format;
use Gibbon\Domain\User\UserGateway;
use Gibbon\Module\Transcripts\Domain\TranscriptGateway;

require_once __DIR__.'/moduleFunctions.php';
checkAndMigrateTranscriptsSchema($pdo);

if (isActionAccessible($guid, $connection2, '/modules/Transcripts/transcripts_edit.php') == false) {
    $page->addError(__('You do not have access to this action.'));
} else {
    $highestAction = getTranscriptViewAction($guid, $connection2);
    if ($highestAction !== 'Generate Transcripts_all') {
        $page->addError(__('You do not have access to this action.'));
    } else {
        $gibbonPersonID = (int)($_GET['gibbonPersonID'] ?? 0);
        $gibbonCourseClassID = (int)($_GET['gibbonCourseClassID'] ?? 0);
        $gibbonSchoolYearTermID = (int)($_GET['gibbonSchoolYearTermID'] ?? 0);
        $gibbonReportingValueID = (int)($_GET['gibbonReportingValueID'] ?? 0);
        $gibbonStudentProgramHistoryID = (int)($_GET['gibbonStudentProgramHistoryID'] ?? 0);

        $backQuery = 'transcripts_view.php';
        if ($gibbonPersonID > 0) {
            $backQuery .= '&gibbonPersonID='.$gibbonPersonID;
            if ($gibbonStudentProgramHistoryID > 0) {
                $backQuery .= '&gibbonStudentProgramHistoryID='.$gibbonStudentProgramHistoryID;
            }
        }

        $transcriptGateway = $container->get(TranscriptGateway::class);
        $record = ($gibbonPersonID > 0 && $gibbonCourseClassID > 0)
            ? $transcriptGateway->getStudentTranscriptRecord($gibbonPersonID, $gibbonCourseClassID, $gibbonSchoolYearTermID, $gibbonReportingValueID)
            : null;

        if (empty($record)) {
            $page->addError(__('The specified record cannot be found.'));
        } else {
            $page->breadcrumbs
                ->add(__('Transcripts'), $backQuery)
                ->add(__('Edit Academic Record'));

            $student = $container->get(UserGateway::class)->getByID($gibbonPersonID, ['preferredName', 'surname']);
            $studentName = Format::name('', $student['preferredName'] ?? '', $student['surname'] ?? '', 'Student', false);

            $form = Form::create('transcriptEdit', $session->get('absoluteURL').'/modules/'.$session->get('module').'/transcripts_editProcess.php');
            $form->setAttribute('target', '_parent');
            $form->addHiddenValue('address', $session->get('address'));
            $form->addHiddenValue('gibbonPersonID', $gibbonPersonID);
            $form->addHiddenValue('gibbonCourseClassID', $gibbonCourseClassID);
            $form->addHiddenValue('gibbonReportingValueID', (int)($record['gibbonReportingValueID'] ?? 0));
            $form->addHiddenValue('gibbonSchoolYearTermID', (int)($record['gibbonSchoolYearTermID'] ?? $gibbonSchoolYearTermID));
            $form->addHiddenValue('gibbonReportingCycleID', (int)($record['gibbonReportingCycleID'] ?? 0));
            $form->addHiddenValue('gibbonStudentProgramHistoryID', $gibbonStudentProgramHistoryID);

            $row = $form->addRow();
                $row->addLabel('studentDisplay', __('Student'));
                $row->addTextField('studentDisplay')->readonly()->setValue($studentName);

            $row = $form->addRow();
                $row->addLabel('courseDisplay', __('Course'));
                $row->addTextField('courseDisplay')->readonly()->setValue(trim(($record['courseCode'] ?? '').' '.($record['courseName'] ?? '')));

            $row = $form->addRow();
                $row->addLabel('yearDisplay', __('Year'));
                $row->addTextField('yearDisplay')->readonly()->setValue($record['schoolYearName'] ?? '');

            $termName = trim((string)($record['termName'] ?? ''));
            if ($termName === '' && $gibbonSchoolYearTermID > 0) {
                $termName = $transcriptGateway->getTermNameByID($gibbonSchoolYearTermID);
            }

            $row = $form->addRow();
                $row->addLabel('termDisplay', __('Term'));
                $row->addTextField('termDisplay')->readonly()->setValue($termName);

            $selectedCycleID = (int)($record['gibbonReportingCycleID'] ?? 0);
            $selectedGradeID = (int)($record['gibbonScaleGradeID'] ?? 0);
            $selectedScaleID = $transcriptGateway->getGradeScaleIDForGrade($selectedGradeID);
            if ($selectedScaleID <= 0) {
                $selectedScaleID = (int)($transcriptGateway->getDefaultGradeScaleIDForClass($gibbonCourseClassID, $selectedCycleID) ?? 0);
            }

            $scaleOptions = $transcriptGateway->getGradeScaleChoices();
            [$gradeOptions, $gradeChained] = $transcriptGateway->getChainedGradeScaleOptions();

            $row = $form->addRow();
                $row->addLabel('gibbonScaleID', __('Grade Scale'));
                $row->addSelect('gibbonScaleID')
                    ->fromArray($scaleOptions)
                    ->placeholder()
                    ->required()
                    ->selected($selectedScaleID > 0 ? $selectedScaleID : '');

            $row = $form->addRow();
                $row->addLabel('gibbonScaleGradeID', __('Grade'));
                $row->addSelect('gibbonScaleGradeID')
                    ->fromArray($gradeOptions)
                    ->chainedTo('gibbonScaleID', $gradeChained)
                    ->placeholder()
                    ->required()
                    ->selected($selectedGradeID > 0 ? $selectedGradeID : '');

            $row = $form->addRow();
                $row->addFooter();
                $row->addSubmit();

            echo $form->getOutput();
        }
    }
}
