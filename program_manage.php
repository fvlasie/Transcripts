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
    $page->breadcrumbs->add(__('Program Dates Management'));

    $page->return->addReturns([
        'success0' => __('Student program dates record saved successfully.'),
        'success1' => __('Student program dates record updated successfully.'),
        'success2' => __('The program was added. It is now available here and in Tuition Billing.'),
        'success3' => __('The program was removed.'),
        'success4' => __('The program was switched. The previous record is closed and the new one is active.'),
        'error6' => __('The switch date must be after the current program started.'),
        'error3' => __('Enter a program name of up to 30 characters, without a comma.'),
        'error4' => __('That program is already in the list.'),
        'error5' => __('That program is used by a student record and was not removed.'),
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
    $typeTable->setDescription(__('These names are shared with Tuition Billing. A program can be removed only when no student record uses it. Names cannot contain a comma.'));
    $typeTable->addColumn('name', __('Program'));
    $typeTable->addColumn('records', __('Student Records'));
    echo $typeTable->render(new DataSet($programTypes));

    $addProgram = Form::create('addProgramType', $session->get('absoluteURL').'/modules/'.$session->get('module').'/program_manageProcess.php');
    $addProgram->addHiddenValue('address', $session->get('address'));
    $addProgram->addHiddenValue('intent', 'addProgram');
    $row = $addProgram->addRow();
        $row->addLabel('newProgramName', __('Add Program'));
        $row->addTextField('newProgramName')->maxLength(30)->required();
    $row = $addProgram->addRow();
        $row->addSubmit();
    echo $addProgram->getOutput();

    $unused = [];
    foreach ($programTypes as $programType) {
        if ((int) $programType['records'] === 0) {
            $unused[$programType['name']] = $programType['name'];
        }
    }
    if (!empty($unused)) {
        $removeProgram = Form::create('removeProgramType', $session->get('absoluteURL').'/modules/'.$session->get('module').'/program_manageProcess.php');
        $removeProgram->addHiddenValue('address', $session->get('address'));
        $removeProgram->addHiddenValue('intent', 'deleteProgram');
        $row = $removeProgram->addRow();
            $row->addLabel('removeProgramName', __('Remove Program'));
            $row->addSelect('removeProgramName')->fromArray($unused)->required();
        $row = $removeProgram->addRow();
            $row->addSubmit(__('Remove'));
        echo $removeProgram->getOutput();
    }
}
