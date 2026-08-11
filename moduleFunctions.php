<?php
// Global helper functions for the Transcripts module

function renderGpaBadge($gpa) {
    $class = 'badge-success';
    if ($gpa < 2.0) {
        $class = 'badge-danger';
    } elseif ($gpa < 3.0) {
        $class = 'badge-warning';
    }
    return "<span class=\"badge {$class}\">" . number_format($gpa, 2) . "</span>";
}
