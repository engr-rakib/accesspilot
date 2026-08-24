<?php
if (!defined('_CORE_ADMIN_')) {
    die('Direct access not permitted');
}

$docKey = trim((string) ($_GET['doc'] ?? ''));
if (!$docKey) {
    echo '<div class="alert alert-danger m-4">No document specified.</div>';
    return;
}

$docKey = str_replace('\\', '/', $docKey);
$docKey = ltrim($docKey, '/');
if (strpos($docKey, '..') !== false) {
    echo '<div class="alert alert-danger m-4">Invalid document path.</div>';
    return;
}

$docsBase = realpath(__DIR__ . '/../../../../docs');
if (!$docsBase) {
    echo '<div class="alert alert-danger m-4">Documents directory not found.</div>';
    return;
}

$docPath = $docsBase . '/' . $docKey . '.md';
$realPath = realpath($docPath);

if (!$realPath || strpos($realPath, $docsBase) !== 0 || !file_exists($realPath) || !is_readable($realPath)) {
    echo '<div class="alert alert-danger m-4">Document not found or unreadable.</div>';
    return;
}

if (pathinfo($realPath, PATHINFO_EXTENSION) !== 'md') {
    echo '<div class="alert alert-danger m-4">Invalid document type.</div>';
    return;
}

$markdown = file_get_contents($realPath);

$relDir = dirname($docKey);
if ($relDir === '.') $relDir = '';
$folderIcons = [
    'Agents' => 'fa-robot',
    'client' => 'fa-user',
    'internal' => 'fa-lock',
    'Technical' => 'fa-cogs',
];
$topFolder = explode('/', $docKey)[0];
$icon = isset($folderIcons[$topFolder]) ? $folderIcons[$topFolder] : 'fas fa-file-alt';

$fileName = pathinfo($realPath, PATHINFO_FILENAME);
$title = str_replace('_', ' ', $fileName);
$title = ucwords(strtolower($title));

function render_markdown(string $text): string
{
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $lines = explode("\n", $text);
    $html = '';
    $inCodeBlock = false;
    $codeContent = '';
    $codeLang = '';
    $inList = false;
    $listType = '';
    $inTable = false;
    $tableHeaders = [];
    $tableRows = [];

    $flushList = function () use (&$html, &$inList, &$listType) {
        if ($inList) {
            $html .= ($listType === 'ol' ? "</ol>\n" : "</ul>\n");
            $inList = false;
            $listType = '';
        }
    };

    $flushTable = function () use (&$html, &$inTable, &$tableHeaders, &$tableRows) {
        if ($inTable && !empty($tableHeaders)) {
            $html .= "<table class=\"md-table\">\n<thead>\n<tr>";
            foreach ($tableHeaders as $h) {
                $html .= '<th>' . htmlspecialchars(trim($h)) . '</th>';
            }
            $html .= "</tr>\n</thead>\n<tbody>\n";
            foreach ($tableRows as $row) {
                $html .= "<tr>";
                foreach ($row as $cell) {
                    $html .= '<td>' . htmlspecialchars(trim($cell)) . '</td>';
                }
                $html .= "</tr>\n";
            }
            $html .= "</tbody>\n</table>\n";
        }
        $inTable = false;
        $tableHeaders = [];
        $tableRows = [];
    };

    $inlineMd = function ($str): string {
        $str = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $str);
        $str = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $str);
        $str = preg_replace('/`([^`]+)`/', '<code class="md-inline-code">$1</code>', $str);
        $str = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2" target="_blank" rel="noopener">$1</a>', $str);
        return $str;
    };

    foreach ($lines as $line) {
        $trimmed = trim($line);

        if (str_starts_with($trimmed, '```')) {
            if ($inCodeBlock) {
                $html .= '<pre class="md-code-block"><code>' . htmlspecialchars($codeContent) . "</code></pre>\n";
                $inCodeBlock = false;
                $codeContent = '';
                $codeLang = '';
            } else {
                $flushList();
                $flushTable();
                $inCodeBlock = true;
                $codeLang = substr($trimmed, 3);
                $codeContent = '';
            }
            continue;
        }
        if ($inCodeBlock) {
            $codeContent .= $line . "\n";
            continue;
        }

        if (preg_match('/^(-{3,}|\*{3,}|_{3,})$/', $trimmed)) {
            $flushList();
            $flushTable();
            $html .= "<hr class=\"md-hr\">\n";
            continue;
        }

        if (preg_match('/^(#{1,6})\s+(.+)$/', $trimmed, $m)) {
            $flushList();
            $flushTable();
            $level = strlen($m[1]);
            $html .= "<h{$level} class=\"md-h{$level}\">{$inlineMd($m[2])}</h{$level}>\n";
            continue;
        }

        if (str_starts_with($trimmed, '|') && substr_count($trimmed, '|') >= 2) {
            $cells = explode('|', trim($trimmed, '|'));
            if (preg_match('/^[\s\-:]+$/', trim($cells[0]))) {
                continue;
            }
            if (!$inTable) {
                $inTable = true;
                $tableHeaders = $cells;
            } else {
                $tableRows[] = $cells;
            }
            continue;
        } else {
            $flushTable();
        }

        if (preg_match('/^(\s*)[\*\-\+]\s+(.+)$/', $trimmed, $m)) {
            if (!$inList || $listType !== 'ul') {
                $flushList();
                $inList = true;
                $listType = 'ul';
                $html .= "<ul>\n";
            }
            $html .= '<li>' . $inlineMd($m[2]) . "</li>\n";
            continue;
        }

        if (preg_match('/^(\s*)\d+\.\s+(.+)$/', $trimmed, $m)) {
            if (!$inList || $listType !== 'ol') {
                $flushList();
                $inList = true;
                $listType = 'ol';
                $html .= "<ol>\n";
            }
            $html .= '<li>' . $inlineMd($m[2]) . "</li>\n";
            continue;
        }

        if ($trimmed === '') {
            $flushList();
            $flushTable();
            continue;
        }

        $flushList();
        $flushTable();
        $html .= '<p class="md-paragraph">' . $inlineMd($trimmed) . "</p>\n";
    }

    $flushList();
    $flushTable();

    if ($inCodeBlock) {
        $html .= '<pre class="md-code-block"><code>' . htmlspecialchars($codeContent) . "</code></pre>\n";
    }

    return $html;
}
?>
<div class="doc-container slide-in-top">
    <div class="status-banner success mb-3">
        <div class="status-banner-icon"><i class="<?= $icon ?>"></i></div>
        <div>
            <div class="status-banner-title"><?= htmlspecialchars($title) ?></div>
            <div class="status-banner-msg">
                <a href="<?= admin_page_url('vendor_console') ?>" class="text-white text-decoration-none"><i class="fas fa-arrow-left me-1"></i>Vendor Console</a>
            </div>
        </div>
    </div>

    <div class="card app-table-card">
        <div class="card-body doc-card-body">
            <div class="doc-content">
                <?= render_markdown($markdown) ?>
            </div>
        </div>
    </div>
</div>
