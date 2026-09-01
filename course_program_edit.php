<?php

use Gibbon\Forms\Form;
use Gibbon\Module\Transcripts\Domain\CourseProgramGateway;

require_once __DIR__.'/moduleFunctions.php';
checkAndMigrateTranscriptsSchema($pdo);

if (isActionAccessible($guid, $connection2, '/modules/Transcripts/course_program.php') == false) {
    $page->addError(__('You do not have access to this action.'));
} else {
    $gibbonCourseID = (int)($_GET['gibbonCourseID'] ?? 0);
    $gibbonSchoolYearID = $_GET['gibbonSchoolYearID'] ?? $session->get('gibbonSchoolYearID');
    $programType = $_GET['programType'] ?? '';

    $backQuery = 'course_program.php&gibbonSchoolYearID='.$gibbonSchoolYearID;
    if (!empty($programType)) {
        $backQuery .= '&programType='.$programType;
    }

    $courseProgramGateway = $container->get(CourseProgramGateway::class);
    $course = $gibbonCourseID > 0 ? $courseProgramGateway->getCourseByID($gibbonCourseID) : null;

    if (empty($course)) {
        $page->addError(__('The specified record cannot be found.'));
    } else {
        $page->breadcrumbs
            ->add(__('Manage Course Programs'), $backQuery)
            ->add(__('Edit'));

        $assigned = $courseProgramGateway->getProgramTypesByCourseCode($course['courseCode']);

        $form = Form::create('courseProgramEdit', $session->get('absoluteURL').'/modules/'.$session->get('module').'/course_program_editProcess.php');
        $form->addHiddenValue('address', $session->get('address'));
        $form->addHiddenValue('gibbonCourseID', $gibbonCourseID);
        $form->addHiddenValue('gibbonSchoolYearID', $gibbonSchoolYearID);
        $form->addHiddenValue('filterProgramType', $programType);
        $form->addHiddenValue('courseCode', $course['courseCode']);

        $row = $form->addRow();
            $row->addLabel('courseCodeDisplay', __('Course Code'));
            $row->addTextField('courseCodeDisplay')->readonly()->setValue($course['courseCode']);

        $row = $form->addRow();
            $row->addLabel('courseNameDisplay', __('Course'));
            $row->addTextField('courseNameDisplay')->readonly()->setValue($course['courseName']);

        $row = $form->addRow();
            $row->addLabel('programTypes', __('Programs'))->description(__('This course will appear on transcripts for the selected programs. Leave empty to use the course level fallback.'));
            $row->addSelect('programTypes')->fromArray(getTranscriptsProgramTypes())->selectMultiple()->selected($assigned);

        $row = $form->addRow();
            $row->addFooter();
            $row->addSubmit();

        echo $form->getOutput();
    }
}
