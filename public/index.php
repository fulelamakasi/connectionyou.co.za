<?php
    require_once __DIR__ . '/../Database.php';

    $allowedPages = ['enquiry', 'entries'];
    $page = isset($_GET['page']) ? trim($_GET['page']) : 'enquiry';

    if (!in_array($page, $allowedPages, true)) {
        $page = 'enquiry';
    }

    $appName = getenv('APP_NAME') ?: 'ConnectionYou';

    $appConfig = [
        'appName'       => $appName,
        'page'          => $page,
        'encryptionKey' => getenv('ENCRYPTION_KEY') ?: '',
        'endpoints'     => [
            'get'     => 'getRestFulResponse.php',
            'process' => 'processRestFulResponse.php',
        ],
    ];

    function e($value)
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="<?= $page === 'enquiry' ? 'index,follow' : 'noindex,nofollow' ?>">
    <title><?= e($page === 'entries' ? 'Entries – ' . $appName : $appName) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Hanken+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/app.css">
</head>
<body class="page-<?= e($page) ?>">

<?php if ($page === 'enquiry') { ?>
    <header class="site-header">
        <a class="brand" href="index.php"><?= e($appName) ?></a>
        <a class="link-quiet" href="index.php?page=entries">View entries</a>
    </header>

    <main class="enquiry">
        <section class="enquiry-intro">
            <h1>Tell us what you need.</h1>
            <p class="lede">Send us a few details and a short message.</p>
        </section>

        <section class="enquiry-form-wrap" aria-labelledby="enquiry-form-title">
            <h2 id="enquiry-form-title" class="visually-hidden">Enquiry form</h2>

            <form id="enquiry-form" class="form" novalidate>
                <div class="form-row">
                    <div class="field">
                        <label for="first_name">First name</label>
                        <input id="first_name" name="first_name" type="text" autocomplete="given-name" required maxlength="255">
                        <p class="field-error" data-error-for="first_name"></p>
                    </div>
                    <div class="field">
                        <label for="last_name">Last name</label>
                        <input id="last_name" name="last_name" type="text" autocomplete="family-name" required maxlength="255">
                        <p class="field-error" data-error-for="last_name"></p>
                    </div>
                </div>

                <div class="form-row">
                    <div class="field">
                        <label for="email">Email</label>
                        <input id="email" name="email" type="email" autocomplete="email" required maxlength="255">
                        <p class="field-error" data-error-for="email"></p>
                    </div>
                    <div class="field">
                        <label for="phone_number">Mobile number</label>
                        <input id="phone_number" name="phone_number" type="tel" autocomplete="tel" inputmode="tel" placeholder="082 123 4567" required>
                        <p class="field-error" data-error-for="phone_number"></p>
                    </div>
                </div>

                <div class="field">
                    <label for="id_number">South African ID number</label>
                    <input id="id_number" name="id_number" type="text" inputmode="numeric" maxlength="13" autocomplete="off">
                    <p class="field-hint">13 digits. Stored as a one-way hash.</p>
                    <p class="field-error" data-error-for="id_number"></p>
                </div>

                <div class="field">
                    <label for="message">Message</label>
                    <textarea id="message" name="message" rows="6" required maxlength="5000"></textarea>
                    <p class="field-error" data-error-for="message"></p>
                </div>

                <div class="form-status" id="enquiry-status" role="status" aria-live="polite"></div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary" id="enquiry-submit">Send enquiry</button>
                </div>
            </form>
        </section>
    </main>

    <footer class="site-footer">
        <p>&copy; <?= date('Y') ?> <?= e($appName) ?></p>
    </footer>
<?php } ?>

<?php if ($page === 'entries') { ?>
    <header class="app-header">
        <a class="brand" href="index.php?page=entries"><?= e($appName) ?></a>
        <a class="link-quiet" href="index.php">Enquiry form</a>
    </header>

    <main class="entries">
        <div class="toolbar">
            <div class="segmented" role="tablist" aria-label="Filter entries" id="entry-filters">
                <button type="button" role="tab" aria-selected="true" data-filter="open">Open <span class="count" data-count="open">0</span></button>
                <button type="button" role="tab" aria-selected="false" data-filter="active">Active <span class="count" data-count="active">0</span></button>
                <button type="button" role="tab" aria-selected="false" data-filter="inactive">Inactive <span class="count" data-count="inactive">0</span></button>
                <button type="button" role="tab" aria-selected="false" data-filter="archived">Archived <span class="count" data-count="archived">0</span></button>
            </div>

            <form class="search" id="search-form" role="search">
                <label for="search-type" class="visually-hidden">Search by</label>
                <select id="search-type" name="type">
                    <option value="userByEmail">Email</option>
                    <option value="userByName">Full name</option>
                    <option value="usersByFirstName">First name</option>
                    <option value="usersByLastName">Last name</option>
                    <option value="userByPhoneNumber">Mobile number</option>
                    <option value="getAllUsersByDate">Date received</option>
                </select>
                <label for="search-query" class="visually-hidden">Search value</label>
                <input id="search-query" name="query" type="search" placeholder="Exact match" autocomplete="off">
                <button type="submit" class="btn btn-secondary">Search</button>
                <button type="button" class="btn btn-ghost" id="search-clear" hidden>Clear</button>
            </form>
        </div>

        <p class="search-summary" id="search-summary" hidden></p>

        <div class="workspace" id="workspace">
            <section class="entry-list-pane" aria-label="Entries">
                <ul class="entry-list" id="entry-list" aria-busy="true"></ul>
                <div class="empty-state" id="list-empty" hidden></div>
            </section>

            <section class="entry-detail-pane" id="entry-detail" aria-live="polite">
                <div class="empty-state">
                    <p>Select an entry to read it.</p>
                </div>
            </section>
        </div>
    </main>

    <dialog class="dialog" id="confirm-dialog">
        <form method="dialog">
            <h2 id="confirm-title"></h2>
            <p id="confirm-message"></p>
            <div class="dialog-actions">
                <button value="cancel" class="btn btn-ghost">Cancel</button>
                <button value="confirm" class="btn btn-primary" id="confirm-ok"></button>
            </div>
        </form>
    </dialog>
<?php } ?>

    <div class="toast-region" id="toast-region" aria-live="polite"></div>

    <script>
        window.APP_CONFIG = <?= json_encode($appConfig, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    </script>
    <script src="js/crypto.js"></script>
    <script src="js/validators.js"></script>
    <script src="js/ui.js"></script>
    <script src="js/getRequests.js"></script>
    <script src="js/postRequests.js"></script>
<?php if ($page === 'enquiry') { ?>
    <script src="js/enquiry.js"></script>
<?php } ?>
<?php if ($page === 'entries') { ?>
    <script src="js/entries.js"></script>
<?php } ?>
</body>
</html>