<style>
    body {
        background-color: <?= $background_color ?>;
        font-family: '<?= $font_style ?>', sans-serif;
        color: <?= $text_color ?>;
    }
    .container { max-width: 1200px; }
    h4 { font-weight: 700; color: <?= $theme_color ?>; }
    h5 { font-weight: 600; color: <?= $theme_color ?>; }

    .form-section {
        background: #fff;
        border-radius: 10px;
        padding: 25px;
        box-shadow: 0 0 12px rgba(0, 0, 0, 0.05);
        margin-bottom: 25px;
    }

    .form-control, .form-select {
        font-size: 14px;
        padding: 8px 12px;
        border-radius: 6px;
        max-width: 100%;
    }
    .form-control[readonly] { background-color: #e9ecef; }
    
    .btn-success { background-color: <?= $btn_add_color ?>; border-color: <?= $btn_add_color ?>; }

    .serial-row {
        background-color: #f8f9fa;
        border-radius: 10px;
        padding: 15px;
        margin-bottom: 10px;
        border: 1px solid #e9ecef;
    }

    .item-number {
        background: <?= $theme_color ?>;
        color: white;
        padding: 5px 15px;
        border-radius: 20px;
        font-weight: 500;
        display: inline-block;
        margin-bottom: 10px;
    }

    .image-preview {
        border: 2px dashed #dee2e6;
        border-radius: 10px;
        padding: 20px;
        text-align: center;
        cursor: pointer;
        min-height: 150px;
    }

    .images-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
        gap: 10px;
        margin-top: 10px;
    }

    .images-grid img {
        max-width: 100px; max-height: 100px;
        border-radius: 10px;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }
    
    .error-feedback { color: #dc3545; font-size: 0.875em; margin-top: 5px; display: none; }
    .is-invalid+.error-feedback { display: block; }

    /* Table Layout Style */
    table { width: 100%; }
    .label-col { width: 150px; font-weight: 500; vertical-align: top; padding-top: 8px; color: #444; }

    @media (max-width: 991.98px) {
        .container { padding-left: 15px; padding-right: 15px; }
        h4 { font-size: 1.5rem; }
        h5 { font-size: 1.25rem; }
        .form-section { padding: 20px; }
        table, tbody, tr, td { display: block; width: 100%; }
        table td { padding: 5px 0 !important; }
        .label-col { margin-top: 10px; margin-bottom: 5px; font-weight: 600; }
        .images-grid { grid-template-columns: repeat(3, minmax(80px, 1fr)); }
    }
</style>