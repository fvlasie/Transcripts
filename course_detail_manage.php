<?php

use Gibbon\Forms\Form;
use Gibbon\Forms\DatabaseFormFactory;
use Gibbon\Services\Format;

require_once __DIR__.'/moduleFunctions.php';
checkAndMigrateTranscriptsSchema($pdo);

if (isActionAccessible($guid, $connection2, '/modules/Transcripts/course_detail_manage.php') == false) {
    $page->addError(__('You do not have access to this action.'));
} else {
    $page->breadcrumbs->add(__('Course Details'));

    $page->return->addReturns([
        'warning1' => __('Some courses were skipped because the level or concentration was not in the list.'),
    ]);

    $gibbonSchoolYearID = (int) ($_GET['gibbonSchoolYearID'] ?? $session->get('gibbonSchoolYearID'));

    echo '<p>'.__('Level and concentration are stored on the course for this school year. Concentration is the learning area. Mode of instruction is set for each student and term on Student Mode.').'</p>';

    $filterForm = Form::create('courseDetailFilter', $session->get('absoluteURL').'/index.php', 'get');
    $filterForm->setFactory(DatabaseFormFactory::create($pdo));
    $filterForm->setClass('noIntBorder w-full');
    $filterForm->addHiddenValue('q', '/modules/'.$session->get('module').'/course_detail_manage.php');
    $row = $filterForm->addRow();
        $row->addLabel('gibbonSchoolYearID', __('School Year'));
        $row->addSelectSchoolYear('gibbonSchoolYearID', 'All')->selected($gibbonSchoolYearID)->required();
    $row = $filterForm->addRow();
        $row->addSearchSubmit($session);
    echo $filterForm->getOutput();

    $courses = $pdo->select(
        'SELECT gibbonCourseID, nameShort, name, courseLevel, gibbonDepartmentID
         FROM gibbonCourse
         WHERE gibbonSchoolYearID = :gibbonSchoolYearID
         ORDER BY nameShort',
        ['gibbonSchoolYearID' => $gibbonSchoolYearID]
    )->fetchAll();

    if (empty($courses)) {
        echo Format::alert(__('This school year has no courses.'), 'warning');
        return;
    }

    $areas = $pdo->select(
        "SELECT gibbonDepartmentID, name FROM gibbonDepartment WHERE type = 'Learning Area' ORDER BY name"
    )->fetchAll();
    $areaOptions = array_column($areas, 'name', 'gibbonDepartmentID');

    $form = Form::create('courseDetails', $session->get('absoluteURL').'/modules/'.$session->get('module').'/course_detail_manageProcess.php');
    $form->addHiddenValue('address', $session->get('address'));
    $form->addHiddenValue('gibbonSchoolYearID', $gibbonSchoolYearID);

    $table = $form->addRow()->addTable()->setClass('smallIntBorder w-full colorOddEven');
    $header = $table->addHeaderRow();
    $header->addContent(__('Course'));
    $header->addContent(__('Level'));
    $header->addContent(__('Concentration'));

    foreach ($courses as $course) {
        $courseID = (int) $course['gibbonCourseID'];
        $tr = $table->addRow();
        $tr->addContent(htmlspecialchars($course['nameShort'].' '.$course['name']));
        $tr->addSelect('courseLevel'.$courseID)->fromArray(getTranscriptsCourseLevels())->required()->selected($course['courseLevel']);
        $tr->addSelect('gibbonDepartmentID'.$courseID)->fromArray($areaOptions)->placeholder()->selected($course['gibbonDepartmentID']);
    }

    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit();

    echo $form->getOutput();
}
