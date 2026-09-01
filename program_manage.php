<?php

use Gibbon\Forms\Form;
use Gibbon\Forms\DatabaseFormFactory;
use Gibbon\Tables\DataTable;
use Gibbon\Services\Format;
use Gibbon\Module\Transcripts\Domain\StudentProgramGateway;

require_once __DIR__.'/moduleFunctions.php';
checkAndMigrateTranscriptsSchema($pdo);

if (isActionAccessible($guid, $connection2, '/modules/Transcripts/program_manage.php') == false) {
    $page->addError(__('You do not have access to this action.'));
} else {
    $page->breadcrumbs->add(__('Program Dates Management'));

    $page->return->addReturns([
        'success0' => __('Student program dates record saved successfully.'),
        'success1' => __('Student program dates record updated successfully.'),
    ]);

    $programGateway = $container->get(StudentProgramGateway::class);

    $gibbonPersonID = $_GET['gibbonPersonID'] ?? '';

    echo '<h2>';
    echo __('Filter');
    echo '</h2>';

    $filterForm = Form::create('programFilter', $session->get('absoluteURL').'/index.php', 'get');
    $filterForm->setFactory(DatabaseFormFactory::create($pdo));
    $filterForm->setClass('noIntBorder w-full');
    $filterForm->addHiddenValue('q', '/modules/'.$session->get('module').'/program_manage.php');

    $row = $filterForm->addRow();
        $row->addLabel('gibbonPersonID', __('Student'));
        $row->addSelectStudent('gibbonPersonID', $session->get('gibbonSchoolYearID'), ['allStudents' => true])->placeholder()->selected($gibbonPersonID);

    $row = $filterForm->addRow();
        $row->addSearchSubmit($session, __('Clear Filters'));

    echo $filterForm->getOutput();

    $criteria = $programGateway->newQueryCriteria(true)
        ->filterBy('gibbonPersonID', $gibbonPersonID)
        ->fromPOST('programRecords');

    if (!empty($_GET['return']) && strpos($_GET['return'], 'success') === 0) {
        $criteria->page(1);
    }

    $programs = $programGateway->queryAllPrograms($criteria);

    $table = DataTable::createPaginated('programRecords', $criteria);
    $table->setTitle(__('Student Program Records'));

    $addAction = $table->addHeaderAction('add', __('Add'))
        ->setURL('/modules/Transcripts/program_manage_add.php')
        ->displayLabel();

    if (!empty($gibbonPersonID)) {
        $addAction->addParam('gibbonPersonID', $gibbonPersonID);
    }

    $table->addColumn('student', __('Student'))
        ->sortable(['surname', 'preferredName'])
        ->format(Format::using('name', ['', 'preferredName', 'surname', 'Student', true]));

    $table->addColumn('programType', __('Program'));
    $table->addColumn('concentration', __('Concentration'));
    $table->addColumn('studentLevel', __('Level'));
    $table->addColumn('status', __('Status'));
    $table->addColumn('startDate', __('Start Date'))->format(Format::using('date', 'startDate'));
    $table->addColumn('graduationDate', __('Graduation'))->format(Format::using('date', 'graduationDate'));

    $actionColumn = $table->addActionColumn()
        ->addParam('gibbonStudentProgramHistoryID');

    if (!empty($gibbonPersonID)) {
        $actionColumn->addParam('gibbonPersonID', $gibbonPersonID);
    }

    $actionColumn->format(function ($row, $actions) {
        $actions->addAction('edit', __('Edit'))
            ->setURL('/modules/Transcripts/program_manage_edit.php');
    });

    echo $table->render($programs);
}
