<style>
    body {
        background-color: <?= $background_color ?>;
        font-family: '<?= $font_style ?>', sans-serif;
        color: <?= $text_color ?>;
    }
    .container { max-width: 1200px; }
    h4, h5 { color: <?= $theme_color ?>; font-weight: 600; }
    
    .form-section {
        background: #fff;
        border-radius: 10px;
        padding: 25px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        margin-bottom: 25px;
    }

    .po-item-card {
        border: 2px solid #dee2e6;
        background: #fdfdfd;
        margin-bottom: 20px;
        padding: 20px;
    }
    
    .po-item-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 15px;
        border-bottom: 1px solid #eee;
        padding-bottom: 10px;
    }

    .po-item-title {
        font-size: 1.2rem;
        font-weight: 600;
        color: #333;
    }

    .po-item-pending {
        font-size: 1rem;
        font-weight: 600;
        color: <?= $theme_color ?>;
    }
    
    .batch-box {
        background: #f8f9fa;
        border: 1px solid #e9ecef;
        border-radius: 10px;
        padding: 20px;
        margin-top: 15px;
        position: relative;
    }
    
    .batch-remove-btn {
        position: absolute;
        top: 10px; right: 10px;
    }
    
    .item-number {
        background: #6c757d;
        color: white;
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 0.75rem;
        margin-bottom: 5px;
        display: inline-block;
    }
    
    .serial-row {
        background: #fff;
        padding: 10px;
        border-radius: 6px;
        margin-bottom: 8px;
        border: 1px solid #dee2e6;
    }
    
    .error-feedback { color: #dc3545; font-size: 0.85em; display: none; margin-top: 4px; }
    .is-invalid + .error-feedback { display: block; }
    
    .batch-image-preview {
        max-width: 80px; max-height: 80px;
        border-radius: 4px; margin-top: 5px;
        cursor: pointer; border: 1px solid #ddd;
    }

    .btn-add-batch {
        background-color: <?= $theme_color ?>;
        color: white;
        border: none;
    }
    .btn-add-batch:hover {
        background-color: #0b5ed7;
        color: white;
    }
</style>