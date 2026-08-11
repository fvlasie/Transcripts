<?php
use Gibbon\Module\Transcripts\Gateway\TranscriptGateway;

if (isActionAccessible($guid, $connection2, '/modules/Transcripts/term_aliases.php') == false) {
    echo "<div class='error'>" . __('You do not have access to this action.') . "</div>";
} else {
    echo "<h2>" . __('Term Name Aliases Management') . "</h2>";

    $page->breadcrumbs->add(__('Term Aliases'));

    $transcriptGateway = new TranscriptGateway($pdo);

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_alias'])) {
        $termID = (int)$_POST['gibbonSchoolYearTermID'];
        $eccName = trim($_POST['ecclesiasticalName']);
        $secName = trim($_POST['secularAlias']);

        $transcriptGateway->saveTermAlias($termID, $eccName, $secName);
        echo "<div class='success mb-4'>" . __('Term alias mapping saved successfully.') . "</div>";
    }

    $aliases = $transcriptGateway->getTermAliases();
    $aliasMap = [];
    foreach ($aliases as $a) {
        $aliasMap[$a['gibbonSchoolYearTermID']] = $a;
    }

    // Fetch terms
    $termsSql = "SELECT gibbonSchoolYearTermID, name FROM gibbonSchoolYearTerm ORDER BY sequenceNumber ASC";
    $termsResult = $pdo->executeQuery([], $termsSql);

    echo "<form method='POST' class='max-w-2xl'>";
    echo "<input type='hidden' name='save_alias' value='1'>";

    echo "<table class='w-full border-collapse border border-gray-300'>";
    echo "<thead><tr class='bg-gray-200'>";
    echo "<th class='p-2 border'>" . __('System Term') . "</th>";
    echo "<th class='p-2 border'>" . __('Ecclesiastical Name') . "</th>";
    echo "<th class='p-2 border'>" . __('Secular Display Alias') . "</th>";
    echo "<th class='p-2 border'>" . __('Action') . "</th>";
    echo "</tr></thead><tbody>";

    while ($term = $termsResult->fetch()) {
        $tID = $term['gibbonSchoolYearTermID'];
        $ecc = $aliasMap[$tID]['ecclesiasticalName'] ?? $term['name'];
        $sec = $aliasMap[$tID]['secularAlias'] ?? '';

        echo "<tr>";
        echo "<td class='p-2 border'>{$term['name']}</td>";
        echo "<td class='p-2 border'><input type='text' name='ecclesiasticalName' value='{$ecc}' class='border p-1 w-full'></td>";
        echo "<td class='p-2 border'><input type='text' name='secularAlias' value='{$sec}' placeholder='e.g., Spring Term' class='border p-1 w-full'></td>";
        echo "<td class='p-2 border text-center'><button type='button' onclick='submitTermAlias(this, {$tID})' class='btn btn-xs btn-secondary'>" . __('Save') . "</button></td>";
        echo "</tr>";
    }
    echo "</tbody></table>";
    echo "</form>";

    echo "<script>
    function submitTermAlias(btn, termID) {
        var row = btn.closest('tr');
        var ecc = row.querySelector('input[name=\"ecclesiasticalName\"]').value;
        var sec = row.querySelector('input[name=\"secularAlias\"]').value;
        
        var form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = '<input type=\"hidden\" name=\"save_alias\" value=\"1\">' +
                         '<input type=\"hidden\" name=\"gibbonSchoolYearTermID\" value=\"' + termID + '\">' +
                         '<input type=\"hidden\" name=\"ecclesiasticalName\" value=\"' + ecc + '\">' +
                         '<input type=\"hidden\" name=\"secularAlias\" value=\"' + sec + '\">';
        document.body.appendChild(form);
        form.submit();
    }
    </script>";
}
