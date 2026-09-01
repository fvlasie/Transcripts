<?php

use Gibbon\Forms\Form;
use Gibbon\Tables\DataTable;
use Gibbon\Services\Format;
use Gibbon\Module\Transcripts\Domain\RegistrarQueryGateway;

require_once __DIR__.'/moduleFunctions.php';
checkAndMigrateTranscriptsSchema($pdo);

if (isActionAccessible($guid, $connection2, '/modules/Transcripts/query_engine.php') == false) {
    $page->addError(__('You do not have access to this action.'));
} else {
    $page->breadcrumbs->add(__('Registrar Reports'));

    $filters = [
        'programType' => $_GET['programType'] ?? '',
        'concentration' => $_GET['concentration'] ?? '',
        'studentLevel' => $_GET['studentLevel'] ?? '',
        'modeOfInstruction' => $_GET['modeOfInstruction'] ?? '',
        'gender' => $_GET['gender'] ?? '',
    ];

    echo '<h2>';
    echo __('Filter');
    echo '</h2>';

    $form = Form::create('registrarFilter', $session->get('absoluteURL').'/index.php', 'get');
    $form->setClass('noIntBorder w-full');
    $form->addHiddenValue('q', '/modules/'.$session->get('module').'/query_engine.php');

    $row = $form->addRow();
        $row->addLabel('programType', __('Program Type'));
        $row->addSelect('programType')->fromArray(getTranscriptsProgramTypes())->placeholder()->selected($filters['programType']);

    $row = $form->addRow();
        $row->addLabel('concentration', __('Concentration'));
        $row->addSelect('concentration')->fromArray(getTranscriptsConcentrations())->placeholder()->selected($filters['concentration']);

    $row = $form->addRow();
        $row->addLabel('studentLevel', __('Student Level'));
        $row->addSelect('studentLevel')->fromArray(getTranscriptsStudentLevels())->placeholder()->selected($filters['studentLevel']);

    $row = $form->addRow();
        $row->addLabel('modeOfInstruction', __('Mode of Instruction'));
        $row->addSelect('modeOfInstruction')->fromArray(getTranscriptsInstructionModes())->placeholder()->selected($filters['modeOfInstruction']);

    $row = $form->addRow();
        $row->addLabel('gender', __('Sex / Gender'));
        $row->addSelect('gender')->fromArray(['M' => __('Male'), 'F' => __('Female')])->placeholder()->selected($filters['gender']);

    $row = $form->addRow();
        $row->addSearchSubmit($session, __('Clear Filters'));

    echo $form->getOutput();

    $queryGateway = $container->get(RegistrarQueryGateway::class);

    $criteria = $queryGateway->newQueryCriteria(true)
        ->sortBy(['surname', 'preferredName'])
        ->fromPOST();

    foreach (array_filter($filters) as $name => $value) {
        $criteria->filterBy($name, $value);
    }

    echo '<h2>';
    echo __('View');
    echo '</h2>';

    $results = $queryGateway->queryStudentRecords($criteria);
    $totalStudents = $queryGateway->countDistinctStudents($criteria);

    $page->addMessage(__('Showing {totalResults} total course entries across {totalStudents} students.', [
        'totalResults' => $results->getResultCount(),
        'totalStudents' => $totalStudents,
    ]));

    $table = DataTable::createPaginated('registrarQuery', $criteria);
    $table->setTitle(__('Results'));

    $table->addColumn('student', __('Student'))
        ->sortable(['surname', 'preferredName'])
        ->format(Format::using('name', ['', 'preferredName', 'surname', 'Student', true]));

    $table->addColumn('programType', __('Program'));
    $table->addColumn('concentration', __('Concentration'));
    $table->addColumn('studentLevel', __('Level'));
    $table->addColumn('course', __('Course'))
        ->format(function ($row) {
            $code = $row['courseCode'] ?? '';
            $name = $row['courseName'] ?? '';

            if ($code && $name) {
                return $code.' - '.$name;
            }

            return $code ?: ($name ?: '-');
        });
    $table->addColumn('modeOfInstruction', __('Mode'));

    echo $table->render($results);
}
