<style>
    body {
        background-color: <?= $background_color ?>;
        font-family: '<?= $font_style ?>';
        color: <?= $text_color ?>;
    }

    .container {
        max-width: 1200px;
    }

    h4 {
        font-weight: 700;
        color: <?= $theme_color ?>;
    }

    .form-section {
        background: #fff;
        border-radius: 15px;
        padding: 30px;
        box-shadow: 0 8px 15px rgba(0, 0, 0, 0.08);
        margin-bottom: 25px;
    }

    .btn-success {
        background-color: <?= $btn_add_color ?>;
        border-color: <?= $btn_add_color ?>;
    }

    .btn-secondary {
        background-color: #6c757d;
        border-color: #6c757d;
    }

    .customer-combo-box,
    .employee-combo-box {
        position: relative;
    }

    .customer-info-box {
        background-color: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        padding: 15px;
        min-height: 100px;
    }

    .serial-check-status {
        margin-top: 10px;
        padding: 10px;
        border-radius: 6px;
        display: none;
        font-size: 0.9rem;
    }

    .serial-check-status.valid {
        background-color: #d1e7dd;
        color: #0f5132;
    }

    .serial-check-status.new {
        background-color: #d1edff;
        color: #0c63e4;
    }

    .serial-check-status.error {
        background-color: #f8d7da;
        color: #721c24;
    }

    .symptom-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 10px;
        padding: 10px 0;
    }

    .is-invalid {
        border-color: #dc3545;
    }

    .is-invalid+.invalid-feedback {
        display: block;
    }

    /* Combo box styling */
    #customer_results,
    #employee_results {
        position: absolute;
        z-index: 1000;
        width: 100%;
        max-height: 200px;
        overflow-y: auto;
        border: 1px solid #dee2e6;
        border-top: none;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        background: white;
        border-radius: 0 0 8px 8px;
    }

    #customer_results .list-group-item,
    #employee_results .list-group-item {
        cursor: pointer;
    }

    .form-control[readonly] {
        background-color: #f0f0f0;
    }
</style>