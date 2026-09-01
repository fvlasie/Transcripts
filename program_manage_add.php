<?php

use Gibbon\Forms\Form;
use Gibbon\Forms\DatabaseFormFactory;

require_once __DIR__.'/moduleFunctions.php';
checkAndMigrateTranscriptsSchema($pdo);

if (isActionAccessible($guid, $connection2, '/modules/Transcripts/program_manage.php') == false) {
    $page->addError(__('You do not have access to this action.'));
} else {
    $filterGibbonPersonID = $_GET['gibbonPersonID'] ?? '';
    $backQuery = 'program_manage.php';
    if (!empty($filterGibbonPersonID)) {
        $backQuery .= '&gibbonPersonID='.$filterGibbonPersonID;
    }

    $page->breadcrumbs
        ->add(__('Program Dates Management'), $backQuery)
        ->add(__('Add'));

    $form = Form::create('programAdd', $session->get('absoluteURL').'/modules/'.$session->get('module').'/program_manageProcess.php');
    $form->setFactory(DatabaseFormFactory::create($pdo));
    $form->addHiddenValue('address', $session->get('address'));
    $form->addHiddenValue('filterGibbonPersonID', $filterGibbonPersonID);

    $row = $form->addRow();
        $row->addLabel('gibbonPersonID', __('Student'))->description(__('Required'));
        $row->addSelectStudent('gibbonPersonID', $session->get('gibbonSchoolYearID'), ['allStudents' => true])->required()->placeholder()->selected($filterGibbonPersonID);

    $row = $form->addRow();
        $row->addLabel('programType', __('Program Type'));
        $row->addSelect('programType')->fromArray(getTranscriptsProgramTypes())->required();

    $row = $form->addRow();
        $row->addLabel('concentration', __('Concentration'));
        $row->addSelect('concentration')->fromArray(getTranscriptsConcentrations())->required();

    $row = $form->addRow();
        $row->addLabel('studentLevel', __('Student Level'));
        $row->addSelect('studentLevel')->fromArray(getTranscriptsStudentLevels())->placeholder();

    $row = $form->addRow();
        $row->addLabel('startDate', __('Start Date'))->description(__('Required'));
        $row->addDate('startDate')->required();

    $row = $form->addRow();
        $row->addLabel('switchDate', __('Switch Date'));
        $row->addDate('switchDate');

    $row = $form->addRow();
        $row->addLabel('graduationDate', __('Graduation Date'));
        $row->addDate('graduationDate');

    $row = $form->addRow();
        $row->addLabel('status', __('Status'));
        $row->addSelect('status')->fromArray(getTranscriptsProgramStatuses())->required()->selected('Active');

    $row = $form->addRow();
        $row->addLabel('notes', __('Notes'));
        $row->addTextArea('notes')->setRows(3);

    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit();

    echo $form->getOutput();
}
