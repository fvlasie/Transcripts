<?php

use Gibbon\Forms\Form;
use Gibbon\Forms\DatabaseFormFactory;
use Gibbon\Tables\DataTable;
use Gibbon\Domain\DataSet;
use Gibbon\Services\Format;
use Gibbon\Module\Transcripts\Domain\StudentProgramGateway;

require_once __DIR__.'/moduleFunctions.php';
checkAndMigrateTranscriptsSchema($pdo);

if (isActionAccessible($guid, $connection2, '/modules/Transcripts/program_manage.php') == false) {
    $page->addError(__('You do not have access to this action.'));
} else {
    $page->breadcrumbs->add(__('Program Management'));

    $page->return->addReturns([
        'success0' => __('Student program dates record saved successfully.'),
        'success1' => __('Student program dates record updated successfully.'),
        'success2' => __('The program was added. It is now available here and in Tuition Billing.'),
        'success3' => __('The program was deleted.'),
        'success4' => __('The program was switched. The previous record is closed and the new one is active.'),
        'error6' => __('The switch date must be after the current program started.'),
        'error3' => __('Enter a program name of up to 30 characters, without a comma.'),
        'error4' => __('That program is already in the list.'),
        'error5' => __('That program is used by a student record and was not deleted.'),
        'error7' => __('Student is required.'),
        'error8' => __('Program Type is required.'),
        'error9' => __('Start Date is required.'),
        'error10' => __('Status is required.'),
        'error1' => __('The program record could not be saved. Check the student, program, start date, and status.'),
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

    if (isset($_GET['programsAdded'])) {
        $added = (int) $_GET['programsAdded'];
        $skipped = (int) ($_GET['programsSkipped'] ?? 0);
        echo Format::alert(sprintf(
            __('Added %1$s program records. %2$s students already had that active program and were skipped.'),
            $added,
            $skipped
        ), $added > 0 ? 'success' : 'warning');
    }

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

    $table->addHeaderAction('page_new', __('Add Cohort'))
        ->setURL('/modules/Transcripts/program_manage_add.php')
        ->addParam('cohort', '1')
        ->displayLabel();

    if (!empty($gibbonPersonID)) {
        $addAction->addParam('gibbonPersonID', $gibbonPersonID);
    }

    $table->addColumn('student', __('Student'))
        ->sortable(['surname', 'preferredName'])
        ->format(Format::using('name', ['', 'preferredName', 'surname', 'Student', true]));

    $table->addColumn('programType', __('Program'));
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

        if (($row['status'] ?? '') === 'Active') {
            $actions->addAction('refresh', __('Switch'))
                ->setURL('/modules/Transcripts/program_manage_edit.php')
                ->addParam('switch', '1')
                ->displayLabel();
        }
    });

    echo $table->render($programs);

    $programTypes = $programGateway->getProgramTypeUsage();
    $typeTable = DataTable::create('programTypes');
    $typeTable->setTitle(__('Programs'));
    $typeTable->setDescription(__('These names are shared with Tuition Billing. A program can be deleted only when no student record uses it. Names cannot contain a comma.'));

    $addProgram = $typeTable->addHeaderAction('add', __('Add'))
        ->setURL('/modules/Transcripts/program_type_add.php')
        ->displayLabel();

    $typeTable->addColumn('name', __('Program'));
    $typeTable->addColumn('records', __('Student Records'));

    $typeActions = $typeTable->addActionColumn()
        ->addParam('name');

    if (!empty($gibbonPersonID)) {
        $addProgram->addParam('gibbonPersonID', $gibbonPersonID);
        $typeActions->addParam('gibbonPersonID', $gibbonPersonID);
    }

    $typeActions->format(function ($program, $actions) {
        if ((int) ($program['records'] ?? 0) !== 0) {
            return;
        }

        $actions->addAction('delete', __('Delete'))
            ->setURL('/modules/Transcripts/program_type_delete.php');
    });

    echo $typeTable->render(new DataSet($programTypes));
}
