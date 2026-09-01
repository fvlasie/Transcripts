<?php

use Gibbon\Forms\Form;
use Gibbon\Forms\DatabaseFormFactory;
use Gibbon\Module\Transcripts\Domain\CourseProgramGateway;

require_once __DIR__.'/moduleFunctions.php';
checkAndMigrateTranscriptsSchema($pdo);

if (isActionAccessible($guid, $connection2, '/modules/Transcripts/course_program.php') == false) {
    $page->addError(__('You do not have access to this action.'));
} else {
    $gibbonSchoolYearID = $_GET['gibbonSchoolYearID'] ?? $session->get('gibbonSchoolYearID');
    $programType = $_GET['programType'] ?? '';

    $backQuery = 'course_program.php&gibbonSchoolYearID='.$gibbonSchoolYearID;
    if (!empty($programType)) {
        $backQuery .= '&programType='.$programType;
    }

    $page->breadcrumbs
        ->add(__('Manage Course Programs'), $backQuery)
        ->add(__('Add'));

    $courseOptions = $container->get(CourseProgramGateway::class)->getCourseSelectBySchoolYear((int)$gibbonSchoolYearID);

    $form = Form::create('courseProgramAdd', $session->get('absoluteURL').'/modules/'.$session->get('module').'/course_program_editProcess.php');
    $form->setFactory(DatabaseFormFactory::create($pdo));
    $form->addHiddenValue('address', $session->get('address'));
    $form->addHiddenValue('gibbonSchoolYearID', $gibbonSchoolYearID);
    $form->addHiddenValue('filterProgramType', $programType);

    $row = $form->addRow();
        $row->addLabel('gibbonCourseID', __('Course'))->description(__('Required'));
        $row->addSelect('gibbonCourseID')->fromArray($courseOptions)->required()->placeholder();

    $row = $form->addRow();
        $row->addLabel('programTypes', __('Programs'))->description(__('This course will appear on transcripts for the selected programs. Leave empty to use the course level fallback.'));
        $row->addSelect('programTypes')->fromArray(getTranscriptsProgramTypes())->selectMultiple()->selected($programType !== '' ? [$programType] : []);

    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit();

    echo $form->getOutput();
}
