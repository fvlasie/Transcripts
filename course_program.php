<?php

use Gibbon\Forms\Form;
use Gibbon\Forms\DatabaseFormFactory;
use Gibbon\Tables\DataTable;
use Gibbon\Module\Transcripts\Domain\CourseProgramGateway;

require_once __DIR__.'/moduleFunctions.php';
checkAndMigrateTranscriptsSchema($pdo);

if (isActionAccessible($guid, $connection2, '/modules/Transcripts/course_program.php') == false) {
    $page->addError(__('You do not have access to this action.'));
} else {
    $page->breadcrumbs->add(__('Manage Course Programs'));

    $page->return->addReturns([
        'success0' => __('Course program assignments saved successfully.'),
    ]);

    $gibbonSchoolYearID = $_GET['gibbonSchoolYearID'] ?? $session->get('gibbonSchoolYearID');
    $programType = $_GET['programType'] ?? '';

    echo '<p>'.__('Assign courses to programs so each transcript only includes grades that belong to that program. Assignments are stored by course code and apply across school years.').'</p>';

    echo '<h2>';
    echo __('Filter');
    echo '</h2>';

    $filterForm = Form::create('courseProgramFilter', $session->get('absoluteURL').'/index.php', 'get');
    $filterForm->setFactory(DatabaseFormFactory::create($pdo));
    $filterForm->setClass('noIntBorder w-full');
    $filterForm->addHiddenValue('q', '/modules/'.$session->get('module').'/course_program.php');

    $row = $filterForm->addRow();
        $row->addLabel('gibbonSchoolYearID', __('School Year'));
        $row->addSelectSchoolYear('gibbonSchoolYearID')->required()->selected($gibbonSchoolYearID);

    $row = $filterForm->addRow();
        $row->addLabel('programType', __('Program'));
        $row->addSelect('programType')->fromArray(getTranscriptsProgramTypes())->placeholder()->selected($programType);

    $row = $filterForm->addRow();
        $row->addSearchSubmit($session, __('Clear Filters'));

    echo $filterForm->getOutput();

    $courseProgramGateway = $container->get(CourseProgramGateway::class);
    $criteria = $courseProgramGateway->newQueryCriteria(true)
        ->filterBy('programType', $programType)
        ->fromPOST('coursePrograms');

    $courses = $courseProgramGateway->queryCourses($criteria, (int)$gibbonSchoolYearID);

    $table = DataTable::createPaginated('coursePrograms', $criteria);
    $table->setTitle(__('Courses'));

    $addAction = $table->addHeaderAction('add', __('Add'))
        ->setURL('/modules/Transcripts/course_program_add.php')
        ->displayLabel();
    $addAction->addParam('gibbonSchoolYearID', $gibbonSchoolYearID);
    if (!empty($programType)) {
        $addAction->addParam('programType', $programType);
    }

    $table->addColumn('courseCode', __('Code'));
    $table->addColumn('courseName', __('Course'));
    $table->addColumn('courseLevel', __('Level'));
    $table->addColumn('programs', __('Programs'))
        ->notSortable()
        ->format(function ($row) {
            return !empty($row['programs']) ? $row['programs'] : __('Not assigned');
        });

    $table->addActionColumn()
        ->addParam('gibbonCourseID')
        ->addParam('gibbonSchoolYearID', $gibbonSchoolYearID)
        ->addParam('programType', $programType)
        ->format(function ($row, $actions) {
            $actions->addAction('edit', __('Edit'))
                ->setURL('/modules/Transcripts/course_program_edit.php');
        });

    echo $table->render($courses);
}
