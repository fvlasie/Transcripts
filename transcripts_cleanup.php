<?php

use Gibbon\Tables\DataTable;
use Gibbon\Domain\DataSet;
use Gibbon\Services\Format;
use Gibbon\Module\Transcripts\Domain\TranscriptGateway;

require_once __DIR__.'/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/Transcripts/transcripts_cleanup.php') == false
    || getTranscriptViewAction($guid, $connection2) !== 'Generate Transcripts_all') {
    $page->addError(__('You do not have access to this action.'));
} else {
    $page->breadcrumbs
        ->add(__('Transcripts'), 'transcripts_view.php')
        ->add(__('Grade Data Cleanup Report'));

    $transcriptGateway = $container->get(TranscriptGateway::class);
    $transcriptURL = $session->get('absoluteURL').'/index.php?q=/modules/'.$session->get('module').'/transcripts_view.php&gibbonPersonID=';

    $studentColumn = function ($row) use ($transcriptURL) {
        if (empty($row['surname'])) {
            return __('Unknown');
        }

        $name = Format::name('', $row['preferredName'], $row['surname'], 'Student', true);

        return '<a href="'.$transcriptURL.(int)$row['gibbonPersonIDStudent'].'">'.htmlspecialchars($name).'</a>';
    };

    $orphans = DataTable::create('orphanValues');
    $orphans->setTitle(__('Values Without a Criterion or Cycle'));
    $orphans->setDescription(__('These reporting values cannot be placed on a transcript. They were usually created by older versions of the Transcripts edit form. Re-enter the grade in place on the transcript, then remove these rows in the database.'));
    $orphans->addColumn('gibbonReportingValueID', __('ID'));
    $orphans->addColumn('student', __('Student'))->format($studentColumn);
    $orphans->addColumn('schoolYearName', __('Year'));
    $orphans->addColumn('class', __('Class'))
        ->format(function ($row) {
            return htmlspecialchars(trim(($row['courseCode'] ?? '').'.'.($row['className'] ?? ''), '.'));
        });
    $orphans->addColumn('value', __('Value'));
    $orphans->addColumn('issue', __('Issue'));
    $orphans->addColumn('timestampModified', __('Last Modified'))->format(Format::using('dateTime', 'timestampModified'));

    echo $orphans->render(new DataSet($transcriptGateway->selectOrphanReportingValues()));

    $duplicates = DataTable::create('duplicateValues');
    $duplicates->setTitle(__('More Than One Grade for a Class and Term'));
    $duplicates->setDescription(__('The transcript shows the grade from the latest reporting cycle. Remove or correct the others in Reports > Write Reports.'));
    $duplicates->addColumn('student', __('Student'))->format($studentColumn);
    $duplicates->addColumn('schoolYearName', __('Year'));
    $duplicates->addColumn('termName', __('Term'));
    $duplicates->addColumn('class', __('Class'))
        ->format(function ($row) {
            return htmlspecialchars($row['courseCode'].'.'.$row['className']);
        });
    $duplicates->addColumn('valueCount', __('Grades'));
    $duplicates->addColumn('grades', __('Cycle / Criterion: Grade'));

    echo $duplicates->render(new DataSet($transcriptGateway->selectDuplicateGradeValues()));
}
