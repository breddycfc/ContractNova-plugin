<?php
/**
 * Plugin Name: Multi-Template Legal Document Generator
 * Description: Generate multiple types of legal documents with live preview
 * Version: 2.1
 * Author: Your Name
 */

// Prevent direct access
if (!defined('ABSPATH')) exit;

// Register shortcode
add_shortcode('legal_documents', 'render_legal_documents');

function render_legal_documents($atts) {
    // Shortcode attributes
    $atts = shortcode_atts(array(
        'templates' => 'rental,employment,nda,service', // Which templates to show
        'template' => '', // Single template to display directly
    ), $atts);
    
    $single_template = !empty($atts['template']);
    
    ob_start();
    ?>
    <div id="legal-doc-generator">
        <style>
            /* Modern Container Styles */
            #legal-doc-generator {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
                color: #2c3e50;
                background: linear-gradient(135deg, #f8f9fa 0%, rgba(19, 94, 237, 0.03) 100%);
                padding: 40px 20px;
                min-height: 100vh;
                position: relative;
            }
            
            #legal-doc-generator::before {
                content: '';
                position: absolute;
                top: 0;
                right: 0;
                width: 400px;
                height: 400px;
                background: radial-gradient(circle, rgba(19, 94, 237, 0.05) 0%, transparent 70%);
                border-radius: 50%;
                transform: translate(50%, -50%);
                z-index: 0;
            }
            
            .ldg-wrapper {
                max-width: 1200px;
                margin: 0 auto;
                position: relative;
                z-index: 1;
            }
            
            /* Header Section */
            .ldg-header {
                text-align: center;
                margin-bottom: 40px;
                position: relative;
            }
            
            .ldg-header::after {
                content: '';
                position: absolute;
                bottom: -10px;
                left: 50%;
                transform: translateX(-50%);
                width: 80px;
                height: 4px;
                background: #135eed;
                border-radius: 2px;
                animation: expandWidth 0.6s ease-out;
            }
            
            @keyframes expandWidth {
                from { width: 0; }
                to { width: 80px; }
            }
            
            .ldg-header h1 {
                font-size: 32px;
                color: #2c3e50;
                margin-bottom: 10px;
                font-weight: 600;
            }
            
            .ldg-header p {
                color: #6c757d;
                font-size: 18px;
            }
            
            /* Template Selector - Modern Card Style */
            .ldg-templates {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
                gap: 20px;
                margin-bottom: 40px;
                max-width: 1000px;
                margin-left: auto;
                margin-right: auto;
            }
            
            .ldg-template-btn {
                background: white;
                border: 2px solid #e1e8ed;
                padding: 20px;
                border-radius: 12px;
                cursor: pointer;
                transition: all 0.3s ease;
                text-align: center;
                box-shadow: 0 2px 4px rgba(0,0,0,0.04);
                position: relative;
            }
            
            .ldg-template-btn:hover {
                border-color: #135eed;
                transform: translateY(-3px);
                box-shadow: 0 8px 16px rgba(19, 94, 237, 0.15);
            }
            
            .ldg-template-btn.active {
                background: linear-gradient(135deg, #135eed 0%, #0f4bc8 100%);
                color: white;
                border-color: transparent;
                transform: scale(1.05);
                box-shadow: 0 4px 15px rgba(19, 94, 237, 0.3);
            }
            
            .ldg-template-icon {
                font-size: 36px;
                display: block;
                margin-bottom: 10px;
                filter: grayscale(0);
            }
            
            .ldg-template-btn.active .ldg-template-icon {
                animation: bounce 0.5s ease;
            }
            
            .ldg-template-btn.active::after {
                content: '';
                position: absolute;
                top: 5px;
                right: 5px;
                width: 12px;
                height: 12px;
                background: white;
                border-radius: 50%;
                animation: pulse 2s infinite;
            }
            
            @keyframes bounce {
                0%, 100% { transform: scale(1); }
                50% { transform: scale(1.1); }
            }
            
            /* Main Content Container */
            .ldg-main-container {
                max-width: 1100px;
                margin: 0 auto;
                background: white;
                border-radius: 16px;
                box-shadow: 0 10px 30px rgba(19, 94, 237, 0.08);
                overflow: hidden;
                border: 1px solid rgba(19, 94, 237, 0.1);
                position: relative;
            }
            
            /* Grid Layout - Contained */
            .ldg-grid {
                display: grid;
                grid-template-columns: 380px 1fr;
                gap: 30px;
                height: auto;
                padding: 20px;
            }
            
            /* Form Section - Modern Sidebar */
            .ldg-form {
                background: #f8f9fa;
                padding: 30px;
                border-radius: 8px;
                overflow-y: auto;
                height: 660px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            }
            
            .ldg-form::-webkit-scrollbar {
                width: 6px;
            }
            
            .ldg-form::-webkit-scrollbar-track {
                background: #f1f1f1;
                border-radius: 3px;
            }
            
            .ldg-form::-webkit-scrollbar-thumb {
                background: #135eed;
                border-radius: 3px;
            }
            
            .ldg-form::-webkit-scrollbar-thumb:hover {
                background: #0f4bc8;
            }
            
            .ldg-form h3 {
                font-size: 20px;
                margin-bottom: 25px;
                color: #135eed;
                font-weight: 600;
                display: flex;
                align-items: center;
                gap: 10px;
                padding-bottom: 15px;
                border-bottom: 2px solid #e1e8ed;
            }
            
            .ldg-form h3::before {
                content: '📝';
                font-size: 24px;
            }
            
            .ldg-form-group {
                margin-bottom: 20px;
            }
            
            .ldg-form-group label {
                display: block;
                margin-bottom: 8px;
                font-weight: 500;
                color: #495057;
                font-size: 14px;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                position: relative;
                padding-left: 10px;
            }
            
            .ldg-form-group label::before {
                content: '';
                position: absolute;
                left: 0;
                top: 50%;
                transform: translateY(-50%);
                width: 3px;
                height: 12px;
                background: #135eed;
                border-radius: 3px;
            }
            
            .ldg-form-group input,
            .ldg-form-group textarea,
            .ldg-form-group select {
                width: 100%;
                padding: 12px 16px;
                border: 2px solid #e1e8ed;
                border-radius: 8px;
                font-size: 15px;
                transition: all 0.3s ease;
                background: white;
                font-family: inherit;
            }
            
            .ldg-form-group input:focus,
            .ldg-form-group textarea:focus,
            .ldg-form-group select:focus {
                outline: none;
                border-color: #135eed;
                box-shadow: 0 0 0 3px rgba(19, 94, 237, 0.1);
            }
            
            .ldg-form-group textarea {
                resize: vertical;
                min-height: 80px;
            }
            
            /* Preview Section - Clean Document Style */
            .ldg-preview-container {
                display: flex;
                flex-direction: column;
                height: 660px;
                border-radius: 8px;
                overflow: hidden;
                box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            }
            
            .ldg-preview-header {
                background: #135eed;
                color: white;
                padding: 20px 30px;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            
            .ldg-preview-header h3 {
                margin: 0;
                font-size: 18px;
                font-weight: 500;
                display: flex;
                align-items: center;
                gap: 10px;
            }
            
            .ldg-preview-header h3::before {
                content: '👁️';
            }
            
            .ldg-preview {
                flex: 1;
                padding: 40px 50px;
                overflow-y: auto;
                background: white;
                font-family: 'Times New Roman', Georgia, serif;
                font-size: 11pt;
                line-height: 1.5;
                color: #000;
            }
            
            .ldg-preview p {
                font-size: 11pt;
                margin-bottom: 8px;
            }
            
            .ldg-preview ol, .ldg-preview ul {
                font-size: 11pt;
            }
            
            .ldg-preview table {
                font-size: 11pt;
            }
            
            .ldg-preview::-webkit-scrollbar {
                width: 8px;
            }
            
            .ldg-preview::-webkit-scrollbar-track {
                background: #f1f1f1;
                border-radius: 4px;
            }
            
            .ldg-preview::-webkit-scrollbar-thumb {
                background: #135eed;
                border-radius: 4px;
            }
            
            .ldg-preview::-webkit-scrollbar-thumb:hover {
                background: #0f4bc8;
            }
            
            /* Document Styles */
            .ldg-doc-title {
                text-align: center;
                font-size: 16pt;
                font-weight: bold;
                margin-bottom: 20px;
                text-transform: uppercase;
                letter-spacing: 1px;
                color: #000;
                padding-bottom: 10px;
            }
            
            .ldg-section {
                margin-bottom: 20px;
                padding-bottom: 15px;
            }
            
            .ldg-section h3 {
                font-size: 12pt;
                margin-bottom: 10px;
                padding-bottom: 5px;
                border-bottom: 1px solid #000;
                color: #000;
                font-weight: bold;
                text-transform: uppercase;
            }
            
            .ldg-field-value {
                border-bottom: 1px solid #000;
                display: inline-block;
                min-width: 150px;
                padding-bottom: 1px;
                font-weight: normal;
                color: #000;
            }
            
            /* Modern Buttons */
            .ldg-buttons {
                padding: 25px;
                text-align: center;
                background: linear-gradient(180deg, #f8f9fa 0%, rgba(19, 94, 237, 0.03) 100%);
                border-top: 1px solid #e1e8ed;
            }
            
            .ldg-btn {
                padding: 12px 30px;
                border: none;
                border-radius: 8px;
                cursor: pointer;
                margin: 0 8px;
                font-size: 15px;
                font-weight: 600;
                transition: all 0.3s ease;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                display: inline-flex;
                align-items: center;
                gap: 8px;
            }
            
            .ldg-btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            }
            
            .ldg-btn.primary {
                background: #135eed;
                color: white;
                position: relative;
                overflow: hidden;
            }
            
            .ldg-btn.primary:hover {
                background: #0f4bc8;
                box-shadow: 0 5px 15px rgba(19, 94, 237, 0.3);
            }
            
            .ldg-btn.success {
                background: white;
                color: #135eed;
                border: 2px solid #135eed;
            }
            
            .ldg-btn.success:hover {
                background: #135eed;
                color: white;
                box-shadow: 0 5px 15px rgba(19, 94, 237, 0.3);
            }
            
            /* Template Fields */
            .template-fields {
                display: none;
                animation: fadeIn 0.3s ease;
            }
            
            .template-fields.active {
                display: block;
            }
            
            @keyframes fadeIn {
                from { opacity: 0; transform: translateY(10px); }
                to { opacity: 1; transform: translateY(0); }
            }
            
            /* Responsive Design */
            @media (max-width: 1024px) {
                .ldg-grid {
                    grid-template-columns: 1fr;
                    height: auto;
                    gap: 20px;
                    padding: 0;
                }
                
                .ldg-form {
                    height: auto;
                    max-height: 500px;
                    border-radius: 8px 8px 0 0;
                }
                
                .ldg-preview-container {
                    height: 600px;
                    border-radius: 0 0 8px 8px;
                }
                
                .ldg-templates {
                    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                    gap: 15px;
                }
                
                .single-template-mode .ldg-grid {
                    padding: 20px;
                }
                
                .single-template-mode .ldg-form {
                    height: auto;
                    max-height: 400px;
                }
            }
            
            @media (max-width: 640px) {
                .ldg-header h1 {
                    font-size: 24px;
                }
                
                .ldg-templates {
                    grid-template-columns: repeat(2, 1fr);
                    gap: 10px;
                }
                
                .ldg-template-btn {
                    padding: 15px 10px;
                }
                
                .ldg-template-icon {
                    font-size: 28px;
                }
                
                .ldg-grid {
                    padding: 0 10px;
                }
                
                .ldg-form {
                    padding: 20px;
                }
                
                .ldg-preview {
                    padding: 20px;
                }
            }
            
            /* Loading State */
            .ldg-loading {
                display: inline-block;
                width: 20px;
                height: 20px;
                border: 3px solid rgba(19, 94, 237, 0.1);
                border-radius: 50%;
                border-top-color: #135eed;
                animation: spin 1s ease-in-out infinite;
            }
            
            @keyframes spin {
                to { transform: rotate(360deg); }
            }
            
            /* Loading Animation */
            @keyframes pulse {
                0% { box-shadow: 0 0 0 0 rgba(19, 94, 237, 0.4); }
                70% { box-shadow: 0 0 0 10px rgba(19, 94, 237, 0); }
                100% { box-shadow: 0 0 0 0 rgba(19, 94, 237, 0); }
            }
            
            /* Single Template Mode */
            .single-template-mode #legal-doc-generator {
                padding: 20px;
                min-height: auto;
                background: #f5f7fa;
            }
            
            .single-template-mode .ldg-main-container {
                margin-top: 0;
                max-width: 1000px;
            }
            
            .single-template-mode .ldg-preview {
                padding: 40px 50px;
            }
            
            .single-template-mode .ldg-grid {
                padding: 30px;
            }
            
            .single-template-mode .ldg-form {
                height: 600px;
            }
            
            .single-template-mode .ldg-preview-container {
                height: 600px;
            }
            
            /* Clean look for single template mode */
            .single-template-mode .ldg-form h3::before {
                content: none;
            }
            
            /* Hide Elementor UI Elements */
            .elementor-editor-active .elementor-element-overlay,
            .elementor-editor-active .elementor-editor-element-settings,
            .elementor-editor-active .elementor-editor-element-remove,
            .elementor-editor-active .elementor-editor-element-edit,
            .elementor-editor-active .elementor-editor-section-settings,
            .elementor-editor-active .elementor-editor-column-settings,
            .elementor-editor-active .elementor-editor-widget-settings,
            .elementor-editor-active .elementor-element-overlay,
            .elementor-add-section,
            .elementor-add-template-button,
            #elementor-panel,
            .elementor-navigator,
            .elementor-panel-footer,
            .elementor-header,
            .elementor-editor-active .elementor-section-wrap > .elementor-add-section,
            .elementor-editor-active .elementor-widget-empty {
                display: none !important;
            }
            
            /* Hide Elementor during PDF generation */
            body.ldg-printing .elementor-element-overlay,
            body.ldg-printing .elementor-editor-element-settings,
            body.ldg-printing .elementor-add-section,
            body.ldg-printing #elementor-panel,
            body.ldg-printing .elementor-navigator,
            body.ldg-printing .elementor-widget:not(#legal-doc-generator),
            body.ldg-printing [class*="elementor-"]:not(.ldg-preview):not(#legal-doc-generator) {
                display: none !important;
                visibility: hidden !important;
            }
            
            /* Print Styles */
            @media print {
                @page {
                    size: A4;
                    margin: 20mm;
                }
                
                /* Hide all Elementor UI elements */
                .elementor-editor-active *,
                .elementor-element-overlay,
                .elementor-editor-element-settings,
                .elementor-editor-element-remove,
                .elementor-editor-element-edit,
                .elementor-editor-section-settings,
                .elementor-editor-column-settings,
                .elementor-editor-widget-settings,
                .elementor-add-section,
                .elementor-add-template-button,
                #elementor-panel,
                .elementor-navigator,
                .elementor-panel-footer,
                .elementor-header,
                .elementor-section-wrap > .elementor-add-section,
                .elementor-widget-empty,
                .elementor-element-editable,
                .elementor-button-wrapper,
                .elementor-widget-container > .elementor-editor-element-settings,
                #elementor-preview-iframe,
                .elementor-document-handle,
                .elementor-add-new-section,
                .e-add-container,
                .e-con-inner > .elementor-editor-element-settings,
                header.elementor-section,
                footer.elementor-section,
                .elementor-location-header,
                .elementor-location-footer,
                .elementor-widget:not(.ldg-preview) {
                    display: none !important;
                    visibility: hidden !important;
                }
                
                #legal-doc-generator {
                    background: white !important;
                    padding: 0 !important;
                }
                
                .ldg-header,
                .ldg-templates,
                .ldg-form,
                .ldg-preview-header,
                .ldg-buttons {
                    display: none !important;
                }
                
                .ldg-main-container {
                    box-shadow: none !important;
                    border-radius: 0 !important;
                    border: none !important;
                    margin: 0 !important;
                    padding: 0 !important;
                }
                
                .ldg-preview {
                    padding: 0 !important;
                    height: auto !important;
                    font-size: 10pt !important;
                    line-height: 1.4 !important;
                    margin: 0 !important;
                    border: none !important;
                }
                
                .ldg-grid {
                    display: block !important;
                    padding: 0 !important;
                }
                
                .ldg-preview-container {
                    box-shadow: none !important;
                    border-radius: 0 !important;
                    height: auto !important;
                }
                
                .ldg-doc-title {
                    font-size: 14pt !important;
                }
                
                .ldg-section h3 {
                    font-size: 11pt !important;
                }
                
                /* Hide all page headers and footers */
                header, footer, nav, aside,
                .site-header, .site-footer,
                .page-header, .page-footer,
                .entry-header, .entry-footer,
                .elementor-location-header,
                .elementor-location-footer,
                [data-elementor-type="header"],
                [data-elementor-type="footer"] {
                    display: none !important;
                }
                
                body * {
                    visibility: hidden !important;
                }
                
                #legal-doc-generator,
                #legal-doc-generator *,
                .ldg-preview,
                .ldg-preview * {
                    visibility: visible !important;
                }
                
                /* Position document at top of page */
                #legal-doc-generator {
                    position: absolute !important;
                    left: 0 !important;
                    top: 0 !important;
                    width: 100% !important;
                }
                
                /* Clean up container styles */
                .ldg-main-container,
                .ldg-grid,
                .ldg-preview-container {
                    position: static !important;
                    transform: none !important;
                    margin: 0 !important;
                    width: 100% !important;
                }
            }
        </style>

        <div class="ldg-wrapper <?php echo $single_template ? 'single-template-mode' : ''; ?>">
            <?php if (!$single_template): ?>
            <!-- Header -->
            <div class="ldg-header">
                <h1>Legal Document Generator</h1>
                <p>Create professional legal documents in minutes</p>
            </div>
            
            <!-- Template Selector -->
            <div class="ldg-templates">
                <?php
                $templates = array(
                    'rental' => array('icon' => '🏠', 'name' => 'Rental Agreement'),
                    'employment' => array('icon' => '💼', 'name' => 'Employment Contract'),
                    'nda' => array('icon' => '🔒', 'name' => 'NDA Agreement'),
                    'service' => array('icon' => '🛠️', 'name' => 'Service Agreement'),
                    'loan' => array('icon' => '💰', 'name' => 'Loan Agreement'),
                    'purchase' => array('icon' => '🛒', 'name' => 'Purchase Agreement'),
                );
                
                $allowed_templates = explode(',', $atts['templates']);
                foreach ($allowed_templates as $template_key) {
                    if (isset($templates[$template_key])) {
                        $template = $templates[$template_key];
                        ?>
                        <button class="ldg-template-btn" onclick="selectTemplate('<?php echo $template_key; ?>')">
                            <span class="ldg-template-icon"><?php echo $template['icon']; ?></span>
                            <div><?php echo $template['name']; ?></div>
                        </button>
                        <?php
                    }
                }
                ?>
            </div>
            <?php endif; ?>
            
            <!-- Main Container -->
            <div class="ldg-main-container">
                <div class="ldg-grid">
                    <!-- Form Section -->
                    <div class="ldg-form">
                        <h3>Document Details</h3>
                        
                        <!-- Rental Agreement Fields -->
                        <div id="rental-fields" class="template-fields">
                            <div class="ldg-form-group">
                                <label>Landlord Name</label>
                                <input type="text" class="ldg-input" data-field="landlord_name" placeholder="Enter full legal name">
                            </div>
                            <div class="ldg-form-group">
                                <label>Landlord Address</label>
                                <textarea class="ldg-input" data-field="landlord_address" rows="2" placeholder="Street address, city, state, zip"></textarea>
                            </div>
                            <div class="ldg-form-group">
                                <label>Tenant Name</label>
                                <input type="text" class="ldg-input" data-field="tenant_name" placeholder="Enter full legal name">
                            </div>
                            <div class="ldg-form-group">
                                <label>Tenant Address</label>
                                <textarea class="ldg-input" data-field="tenant_address" rows="2" placeholder="Street address, city, state, zip"></textarea>
                            </div>
                            <div class="ldg-form-group">
                                <label>Property Address</label>
                                <textarea class="ldg-input" data-field="property_address" rows="2" placeholder="Complete rental property address"></textarea>
                            </div>
                            <div class="ldg-form-group">
                                <label>Monthly Rent</label>
                                <input type="number" class="ldg-input" data-field="rent" step="0.01" placeholder="0.00">
                            </div>
                            <div class="ldg-form-group">
                                <label>Security Deposit</label>
                                <input type="number" class="ldg-input" data-field="deposit" step="0.01" placeholder="0.00">
                            </div>
                            <div class="ldg-form-group">
                                <label>Lease Start Date</label>
                                <input type="date" class="ldg-input" data-field="start_date">
                            </div>
                            <div class="ldg-form-group">
                                <label>Lease End Date</label>
                                <input type="date" class="ldg-input" data-field="end_date">
                            </div>
                        </div>
                        
                        <!-- Employment Contract Fields -->
                        <div id="employment-fields" class="template-fields">
                            <div class="ldg-form-group">
                                <label>Employer Name</label>
                                <input type="text" class="ldg-input" data-field="employer_name" placeholder="Company name">
                            </div>
                            <div class="ldg-form-group">
                                <label>Employer Address</label>
                                <textarea class="ldg-input" data-field="employer_address" rows="2" placeholder="Company address"></textarea>
                            </div>
                            <div class="ldg-form-group">
                                <label>Employee Name</label>
                                <input type="text" class="ldg-input" data-field="employee_name" placeholder="Full legal name">
                            </div>
                            <div class="ldg-form-group">
                                <label>Employee Address</label>
                                <textarea class="ldg-input" data-field="employee_address" rows="2" placeholder="Home address"></textarea>
                            </div>
                            <div class="ldg-form-group">
                                <label>Job Title</label>
                                <input type="text" class="ldg-input" data-field="job_title" placeholder="Position title">
                            </div>
                            <div class="ldg-form-group">
                                <label>Department</label>
                                <input type="text" class="ldg-input" data-field="department" placeholder="Department name">
                            </div>
                            <div class="ldg-form-group">
                                <label>Annual Salary</label>
                                <input type="number" class="ldg-input" data-field="salary" step="0.01" placeholder="0.00">
                            </div>
                            <div class="ldg-form-group">
                                <label>Start Date</label>
                                <input type="date" class="ldg-input" data-field="emp_start_date">
                            </div>
                            <div class="ldg-form-group">
                                <label>Working Hours</label>
                                <input type="text" class="ldg-input" data-field="working_hours" placeholder="e.g., 9:00 AM - 5:00 PM">
                            </div>
                            <div class="ldg-form-group">
                                <label>Vacation Days</label>
                                <input type="number" class="ldg-input" data-field="vacation_days" placeholder="Annual vacation days">
                            </div>
                        </div>
                        
                        <!-- NDA Agreement Fields -->
                        <div id="nda-fields" class="template-fields">
                            <div class="ldg-form-group">
                                <label>Disclosing Party Name</label>
                                <input type="text" class="ldg-input" data-field="disclosing_party" placeholder="Party sharing information">
                            </div>
                            <div class="ldg-form-group">
                                <label>Disclosing Party Address</label>
                                <textarea class="ldg-input" data-field="disclosing_address" rows="2" placeholder="Complete address"></textarea>
                            </div>
                            <div class="ldg-form-group">
                                <label>Receiving Party Name</label>
                                <input type="text" class="ldg-input" data-field="receiving_party" placeholder="Party receiving information">
                            </div>
                            <div class="ldg-form-group">
                                <label>Receiving Party Address</label>
                                <textarea class="ldg-input" data-field="receiving_address" rows="2" placeholder="Complete address"></textarea>
                            </div>
                            <div class="ldg-form-group">
                                <label>Purpose of Disclosure</label>
                                <textarea class="ldg-input" data-field="nda_purpose" rows="3" placeholder="Describe the purpose of sharing confidential information"></textarea>
                            </div>
                            <div class="ldg-form-group">
                                <label>Confidentiality Period (Years)</label>
                                <input type="number" class="ldg-input" data-field="nda_period" min="1" value="2">
                            </div>
                            <div class="ldg-form-group">
                                <label>Effective Date</label>
                                <input type="date" class="ldg-input" data-field="nda_date">
                            </div>
                        </div>
                        
                        <!-- Service Agreement Fields -->
                        <div id="service-fields" class="template-fields">
                            <div class="ldg-form-group">
                                <label>Service Provider Name</label>
                                <input type="text" class="ldg-input" data-field="provider_name" placeholder="Provider name or company">
                            </div>
                            <div class="ldg-form-group">
                                <label>Provider Address</label>
                                <textarea class="ldg-input" data-field="provider_address" rows="2" placeholder="Complete address"></textarea>
                            </div>
                            <div class="ldg-form-group">
                                <label>Client Name</label>
                                <input type="text" class="ldg-input" data-field="client_name" placeholder="Client name or company">
                            </div>
                            <div class="ldg-form-group">
                                <label>Client Address</label>
                                <textarea class="ldg-input" data-field="client_address" rows="2" placeholder="Complete address"></textarea>
                            </div>
                            <div class="ldg-form-group">
                                <label>Services Description</label>
                                <textarea class="ldg-input" data-field="services_desc" rows="4" placeholder="Detailed description of services to be provided"></textarea>
                            </div>
                            <div class="ldg-form-group">
                                <label>Service Fee</label>
                                <input type="number" class="ldg-input" data-field="service_fee" step="0.01" placeholder="0.00">
                            </div>
                            <div class="ldg-form-group">
                                <label>Payment Terms</label>
                                <select class="ldg-input" data-field="payment_terms">
                                    <option value="">Select payment terms...</option>
                                    <option value="Net 30">Net 30 Days</option>
                                    <option value="Net 15">Net 15 Days</option>
                                    <option value="Upon Receipt">Due Upon Receipt</option>
                                    <option value="50% Upfront">50% Upfront, 50% on Completion</option>
                                </select>
                            </div>
                            <div class="ldg-form-group">
                                <label>Start Date</label>
                                <input type="date" class="ldg-input" data-field="service_start">
                            </div>
                            <div class="ldg-form-group">
                                <label>End Date</label>
                                <input type="date" class="ldg-input" data-field="service_end">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Preview Section -->
                    <div class="ldg-preview-container">
                        <div class="ldg-preview-header">
                            <h3>Document Preview</h3>
                            <div>
                                <button class="ldg-btn primary" onclick="ldgRefreshPreview()">Refresh</button>
                                <button class="ldg-btn success" onclick="ldgDownloadPDF()">Download as PDF</button>
                            </div>
                        </div>
                        <div class="ldg-preview" id="ldg-preview">
                            <?php if ($single_template): ?>
                            <!-- Content will be loaded by JavaScript -->
                            <?php else: ?>
                            <div style="text-align: center; color: #666; padding: 100px 20px;">
                                <div style="font-size: 48px; margin-bottom: 20px; opacity: 0.3;">📄</div>
                                <h3 style="color: #333; font-weight: 500; font-size: 20px;">Select a Template to Begin</h3>
                                <p style="font-size: 14px; margin-top: 10px; color: #666;">Choose a document type from the options above to start creating your legal document.</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    var currentTemplate = <?php echo $single_template ? "'" . $atts['template'] . "'" : 'null'; ?>;
    var documentData = {};
    var singleTemplateMode = <?php echo $single_template ? 'true' : 'false'; ?>;
    
    jQuery(document).ready(function($) {
        // Auto-update on input
        $(document).on('input change', '.ldg-input', function() {
            var field = $(this).data('field');
            documentData[field] = $(this).val();
            ldgUpdatePreview();
        });

    // Additional cleanup for Elementor elements
    if (window.elementor) {
        // Hide Elementor UI when our plugin is active
        jQuery(document).ready(function($) {
            if ($('#legal-doc-generator').length) {
                // Hide Elementor edit buttons
                $('.elementor-element-edit-mode').removeClass('elementor-element-edit-mode');
                $('.elementor-element-editable').removeClass('elementor-element-editable');
                
                // Remove Elementor click handlers on our elements
                $('#legal-doc-generator').off('click.elementor');
                $('#legal-doc-generator *').off('click.elementor');
            }
        });
    }
        
        // Auto-load template if in single template mode
        if (singleTemplateMode && currentTemplate) {
            ldgUpdatePreview();
        }
    });

    function selectTemplate(templateId) {
        if (singleTemplateMode) return; // Prevent template switching in single mode
        
        currentTemplate = templateId;
        
        // Update button states
        document.querySelectorAll('.ldg-template-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        event.target.closest('.ldg-template-btn').classList.add('active');
        
        // Hide all field sets
        document.querySelectorAll('.template-fields').forEach(fields => {
            fields.classList.remove('active');
        });
        
        // Show selected template fields
        document.getElementById(templateId + '-fields').classList.add('active');
        
        // Clear form data
        documentData = {};
        document.querySelectorAll('.ldg-input').forEach(input => {
            input.value = '';
        });
        
        // Update preview with template
        ldgUpdatePreview();
        
        // Scroll to form
        document.querySelector('.ldg-main-container').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function ldgUpdatePreview() {
        if (!currentTemplate) return;
        
        var previewHTML = '';
        
        switch(currentTemplate) {
            case 'rental':
                previewHTML = generateRentalAgreement();
                break;
            case 'employment':
                previewHTML = generateEmploymentContract();
                break;
            case 'nda':
                previewHTML = generateNDAgreement();
                break;
            case 'service':
                previewHTML = generateServiceAgreement();
                break;
            case 'loan':
                previewHTML = generateLoanAgreement();
                break;
            case 'purchase':
                previewHTML = generatePurchaseAgreement();
                break;
        }
        
        document.getElementById('ldg-preview').innerHTML = previewHTML;
    }

    function generateRentalAgreement() {
        return `
            <div class="ldg-doc-title">RESIDENTIAL LEASE AGREEMENT</div>
            
            <p style="text-align: center; margin-bottom: 30px;">This Agreement is made on: <strong>${new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}</strong></p>
            
            <div class="ldg-section">
                <h3>1. PARTIES TO THIS AGREEMENT</h3>
                <p><strong>LANDLORD:</strong><br>
                <span class="ldg-field-value">${documentData.landlord_name || '[Landlord Full Legal Name]'}</span><br>
                <span class="ldg-field-value">${documentData.landlord_address || '[Landlord Complete Address]'}</span></p>
                
                <p style="margin-top: 20px;"><strong>TENANT:</strong><br>
                <span class="ldg-field-value">${documentData.tenant_name || '[Tenant Full Legal Name]'}</span><br>
                <span class="ldg-field-value">${documentData.tenant_address || '[Tenant Complete Address]'}</span></p>
            </div>
            
            <div class="ldg-section">
                <h3>2. PROPERTY DETAILS</h3>
                <p>The Landlord agrees to rent to the Tenant the property located at:</p>
                <p style="margin-left: 20px;"><span class="ldg-field-value">${documentData.property_address || '[Complete Property Address]'}</span></p>
            </div>
            
            <div class="ldg-section">
                <h3>3. LEASE TERMS</h3>
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="padding: 10px 0;"><strong>Monthly Rent:</strong></td>
                        <td>$            <span class="ldg-field-value">${documentData.rent ? parseFloat(documentData.rent).toFixed(2) : '[Amount]'}</span></td>
                    </tr>
                    <tr>
                        <td style="padding: 10px 0;"><strong>Security Deposit:</strong></td>
                        <td>$<span class="ldg-field-value">${documentData.deposit ? parseFloat(documentData.deposit).toFixed(2) : '[Amount]'}</span></td>
                    </tr>
                    <tr>
                        <td style="padding: 10px 0;"><strong>Lease Start Date:</strong></td>
                        <td><span class="ldg-field-value">${documentData.start_date || '[Start Date]'}</span></td>
                    </tr>
                    <tr>
                        <td style="padding: 10px 0;"><strong>Lease End Date:</strong></td>
                        <td><span class="ldg-field-value">${documentData.end_date || '[End Date]'}</span></td>
                    </tr>
                </table>
            </div>
            
            <div class="ldg-section">
                <h3>4. TERMS AND CONDITIONS</h3>
                <ol style="line-height: 2;">
                    <li>Rent is due on the 1st day of each month</li>
                    <li>Late payment fee of $50.00 will be charged after the 5th day</li>
                    <li>Tenant is responsible for all utilities unless otherwise specified</li>
                    <li>No smoking is permitted on the premises</li>
                    <li>No pets allowed without prior written consent from Landlord</li>
                    <li>Tenant must give 30 days written notice before vacating</li>
                    <li>Property must be returned in the same condition as received</li>
                </ol>
            </div>
            
            ${generateSignatureSection()}
        `;
    }
    
    function generateEmploymentContract() {
        return `
            <div class="ldg-doc-title">EMPLOYMENT CONTRACT</div>
            
            <p style="text-align: center; margin-bottom: 20px; font-size: 11pt;">This Agreement is made on: <strong>${new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}</strong></p>
            
            <div class="ldg-section">
                <h3>1. PARTIES</h3>
                <p style="margin-bottom: 10px; font-size: 11pt;"><strong>EMPLOYER:</strong><br>
                <span class="ldg-field-value">${documentData.employer_name || '[Company Name]'}</span><br>
                <span class="ldg-field-value">${documentData.employer_address || '[Company Address]'}</span></p>
                
                <p style="margin-bottom: 10px; font-size: 11pt;"><strong>EMPLOYEE:</strong><br>
                <span class="ldg-field-value">${documentData.employee_name || '[Employee Full Name]'}</span><br>
                <span class="ldg-field-value">${documentData.employee_address || '[Employee Address]'}</span></p>
            </div>
            
            <div class="ldg-section">
                <h3>2. POSITION DETAILS</h3>
                <table style="width: 100%; border-collapse: collapse; font-size: 11pt;">
                    <tr>
                        <td style="padding: 5px 0; width: 200px;"><strong>Job Title:</strong></td>
                        <td><span class="ldg-field-value">${documentData.job_title || '[Position Title]'}</span></td>
                    </tr>
                    <tr>
                        <td style="padding: 5px 0;"><strong>Department:</strong></td>
                        <td><span class="ldg-field-value">${documentData.department || '[Department]'}</span></td>
                    </tr>
                    <tr>
                        <td style="padding: 5px 0;"><strong>Start Date:</strong></td>
                        <td><span class="ldg-field-value">${documentData.emp_start_date || '[Start Date]'}</span></td>
                    </tr>
                    <tr>
                        <td style="padding: 5px 0;"><strong>Working Hours:</strong></td>
                        <td><span class="ldg-field-value">${documentData.working_hours || '[Hours]'}</span></td>
                    </tr>
                </table>
            </div>
            
            <div class="ldg-section">
                <h3>3. COMPENSATION & BENEFITS</h3>
                <table style="width: 100%; border-collapse: collapse; font-size: 11pt;">
                    <tr>
                        <td style="padding: 5px 0; width: 200px;"><strong>Annual Salary:</strong></td>
                        <td>$<span class="ldg-field-value">${documentData.salary ? parseFloat(documentData.salary).toFixed(2) : '[Amount]'}</span></td>
                    </tr>
                    <tr>
                        <td style="padding: 5px 0;"><strong>Vacation Days:</strong></td>
                        <td><span class="ldg-field-value">${documentData.vacation_days || '[Number]'}</span> days per year</td>
                    </tr>
                </table>
            </div>
            
            <div class="ldg-section">
                <h3>4. TERMS OF EMPLOYMENT</h3>
                <ol style="font-size: 11pt; line-height: 1.6; margin-left: 20px;">
                    <li>This is an at-will employment relationship</li>
                    <li>Employee must maintain confidentiality of company information</li>
                    <li>Employee is eligible for company benefits after 90 days</li>
                    <li>Performance reviews will be conducted annually</li>
                    <li>Two weeks notice is required for resignation</li>
                </ol>
            </div>
            
            ${generateSignatureSection()}
        `;
    }
    
    function generateNDAgreement() {
        return `
            <div class="ldg-doc-title">NON-DISCLOSURE AGREEMENT</div>
            
            <p style="text-align: center; margin-bottom: 20px; font-size: 11pt;">Effective Date: <strong>${documentData.nda_date || new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}</strong></p>
            
            <div class="ldg-section">
                <h3>1. PARTIES</h3>
                <p style="margin-bottom: 10px; font-size: 11pt;"><strong>DISCLOSING PARTY:</strong><br>
                <span class="ldg-field-value">${documentData.disclosing_party || '[Disclosing Party Name]'}</span><br>
                <span class="ldg-field-value">${documentData.disclosing_address || '[Address]'}</span></p>
                
                <p style="margin-bottom: 10px; font-size: 11pt;"><strong>RECEIVING PARTY:</strong><br>
                <span class="ldg-field-value">${documentData.receiving_party || '[Receiving Party Name]'}</span><br>
                <span class="ldg-field-value">${documentData.receiving_address || '[Address]'}</span></p>
            </div>
            
            <div class="ldg-section">
                <h3>2. PURPOSE</h3>
                <p style="font-size: 11pt;">The purpose of this Agreement is to prevent the unauthorized disclosure of Confidential Information as defined below. The parties intend to enter into discussions regarding:</p>
                <p style="margin: 15px; padding: 10px; background: #f8f9fa; border-left: 3px solid #000; font-size: 11pt;">
                    ${documentData.nda_purpose || '[Purpose of disclosure]'}
                </p>
            </div>
            
            <div class="ldg-section">
                <h3>3. CONFIDENTIAL INFORMATION</h3>
                <p style="font-size: 11pt;">For purposes of this Agreement, "Confidential Information" means all information or material that has or could have commercial value or other utility in the business in which Disclosing Party is engaged.</p>
            </div>
            
            <div class="ldg-section">
                <h3>4. OBLIGATIONS</h3>
                <ol style="font-size: 11pt; line-height: 1.6; margin-left: 20px;">
                    <li>Receiving Party shall hold and maintain the Confidential Information in strict confidence</li>
                    <li>Receiving Party shall not disclose Confidential Information to third parties</li>
                    <li>Receiving Party shall not use Confidential Information for any purpose except to evaluate and engage in discussions concerning a potential business relationship</li>
                    <li>This Agreement shall remain in effect for <strong>${documentData.nda_period || '2'} years</strong></li>
                </ol>
            </div>
            
            ${generateSignatureSection()}
        `;
    }
    
    function generateServiceAgreement() {
        return `
            <div class="ldg-doc-title">SERVICE AGREEMENT</div>
            
            <p style="text-align: center; margin-bottom: 30px;">This Agreement is made on: <strong>${new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}</strong></p>
            
            <div class="ldg-section">
                <h3>1. PARTIES</h3>
                <p><strong>SERVICE PROVIDER:</strong><br>
                <span class="ldg-field-value">${documentData.provider_name || '[Provider Name]'}</span><br>
                <span class="ldg-field-value">${documentData.provider_address || '[Provider Address]'}</span></p>
                
                <p style="margin-top: 20px;"><strong>CLIENT:</strong><br>
                <span class="ldg-field-value">${documentData.client_name || '[Client Name]'}</span><br>
                <span class="ldg-field-value">${documentData.client_address || '[Client Address]'}</span></p>
            </div>
            
            <div class="ldg-section">
                <h3>2. SERVICES TO BE PROVIDED</h3>
                <p style="margin: 20px; padding: 15px; background: #f8f9fa; border-left: 4px solid #000;">
                    ${documentData.services_desc || '[Detailed description of services to be provided]'}
                </p>
            </div>
            
            <div class="ldg-section">
                <h3>3. COMPENSATION & PAYMENT TERMS</h3>
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="padding: 10px 0; width: 40%;"><strong>Service Fee:</strong></td>
                        <td>$<span class="ldg-field-value">${documentData.service_fee ? parseFloat(documentData.service_fee).toFixed(2) : '[Amount]'}</span></td>
                    </tr>
                    <tr>
                        <td style="padding: 10px 0;"><strong>Payment Terms:</strong></td>
                        <td><span class="ldg-field-value">${documentData.payment_terms || '[Payment Terms]'}</span></td>
                    </tr>
                </table>
            </div>
            
            <div class="ldg-section">
                <h3>4. PROJECT TIMELINE</h3>
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="padding: 10px 0; width: 40%;"><strong>Start Date:</strong></td>
                        <td><span class="ldg-field-value">${documentData.service_start || '[Start Date]'}</span></td>
                    </tr>
                    <tr>
                        <td style="padding: 10px 0;"><strong>End Date:</strong></td>
                        <td><span class="ldg-field-value">${documentData.service_end || '[End Date]'}</span></td>
                    </tr>
                </table>
            </div>
            
            <div class="ldg-section">
                <h3>5. TERMS AND CONDITIONS</h3>
                <ol style="line-height: 2;">
                    <li>Provider will perform services as an independent contractor</li>
                    <li>All work product shall become property of the Client upon payment</li>
                    <li>Provider warrants all work will be original and not infringe on any copyrights</li>
                    <li>Either party may terminate with 15 days written notice</li>
                </ol>
            </div>
            
            ${generateSignatureSection()}
        `;
    }
    
    function generateSignatureSection() {
        return `
            <div style="margin-top: 50px; page-break-inside: avoid;">
                <p style="text-align: center; margin-bottom: 30px; font-size: 11pt;"><strong>IN WITNESS WHEREOF, the parties have executed this Agreement as of the date first written above.</strong></p>
                
                <table style="width: 100%; margin-top: 40px; font-size: 11pt;">
                    <tr>
                        <td style="width: 45%; text-align: left;">
                            <div style="border-bottom: 1px solid #000; margin-bottom: 5px; height: 30px;"></div>
                            <p style="margin: 5px 0;">Signature</p>
                            <p style="margin: 5px 0;">Date: _________________</p>
                        </td>
                        <td style="width: 10%;"></td>
                        <td style="width: 45%; text-align: left;">
                            <div style="border-bottom: 1px solid #000; margin-bottom: 5px; height: 30px;"></div>
                            <p style="margin: 5px 0;">Signature</p>
                            <p style="margin: 5px 0;">Date: _________________</p>
                        </td>
                    </tr>
                </table>
            </div>
        `;
    }

    function generateLoanAgreement() {
        return `
            <div class="ldg-doc-title">LOAN AGREEMENT</div>
            
            <p style="text-align: center; margin-bottom: 20px; font-size: 11pt;">This Agreement is made on: <strong>${new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}</strong></p>
            
            <div class="ldg-section">
                <h3>1. PARTIES</h3>
                <p style="margin-bottom: 10px; font-size: 11pt;"><strong>LENDER:</strong><br>
                <span class="ldg-field-value">${documentData.lender_name || '[Lender Name]'}</span><br>
                <span class="ldg-field-value">${documentData.lender_address || '[Lender Address]'}</span></p>
                
                <p style="margin-bottom: 10px; font-size: 11pt;"><strong>BORROWER:</strong><br>
                <span class="ldg-field-value">${documentData.borrower_name || '[Borrower Name]'}</span><br>
                <span class="ldg-field-value">${documentData.borrower_address || '[Borrower Address]'}</span></p>
            </div>
            
            <div class="ldg-section">
                <h3>2. LOAN TERMS</h3>
                <table style="width: 100%; border-collapse: collapse; font-size: 11pt;">
                    <tr>
                        <td style="padding: 5px 0; width: 200px;"><strong>Loan Amount:</strong></td>
                        <td>$<span class="ldg-field-value">${documentData.loan_amount ? parseFloat(documentData.loan_amount).toFixed(2) : '[Amount]'}</span></td>
                    </tr>
                    <tr>
                        <td style="padding: 5px 0;"><strong>Interest Rate:</strong></td>
                        <td><span class="ldg-field-value">${documentData.interest_rate || '[Rate]'}</span>% per annum</td>
                    </tr>
                    <tr>
                        <td style="padding: 5px 0;"><strong>Loan Term:</strong></td>
                        <td><span class="ldg-field-value">${documentData.loan_term || '[Term]'}</span> months</td>
                    </tr>
                    <tr>
                        <td style="padding: 5px 0;"><strong>Monthly Payment:</strong></td>
                        <td>$<span class="ldg-field-value">${documentData.monthly_payment || '[Calculate]'}</span></td>
                    </tr>
                    <tr>
                        <td style="padding: 5px 0;"><strong>First Payment Date:</strong></td>
                        <td><span class="ldg-field-value">${documentData.first_payment || '[Date]'}</span></td>
                    </tr>
                </table>
            </div>
            
            <div class="ldg-section">
                <h3>3. REPAYMENT</h3>
                <p style="font-size: 11pt;">Borrower agrees to repay the loan in equal monthly installments of principal and interest.</p>
            </div>
            
            ${generateSignatureSection()}
        `;
    }
    
    function generatePurchaseAgreement() {
        return `
            <div class="ldg-doc-title">PURCHASE AGREEMENT</div>
            
            <p style="text-align: center; margin-bottom: 20px; font-size: 11pt;">This Agreement is made on: <strong>${new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}</strong></p>
            
            <div class="ldg-section">
                <h3>1. PARTIES</h3>
                <p style="margin-bottom: 10px; font-size: 11pt;"><strong>SELLER:</strong><br>
                <span class="ldg-field-value">${documentData.seller_name || '[Seller Name]'}</span><br>
                <span class="ldg-field-value">${documentData.seller_address || '[Seller Address]'}</span></p>
                
                <p style="margin-bottom: 10px; font-size: 11pt;"><strong>BUYER:</strong><br>
                <span class="ldg-field-value">${documentData.buyer_name || '[Buyer Name]'}</span><br>
                <span class="ldg-field-value">${documentData.buyer_address || '[Buyer Address]'}</span></p>
            </div>
            
            <div class="ldg-section">
                <h3>2. ITEM DESCRIPTION</h3>
                <p style="margin: 15px; padding: 10px; background: #f8f9fa; border-left: 3px solid #000; font-size: 11pt;">
                    ${documentData.item_description || '[Description of item/property being sold]'}
                </p>
            </div>
            
            <div class="ldg-section">
                <h3>3. PURCHASE TERMS</h3>
                <table style="width: 100%; border-collapse: collapse; font-size: 11pt;">
                    <tr>
                        <td style="padding: 5px 0; width: 200px;"><strong>Purchase Price:</strong></td>
                        <td>$<span class="ldg-field-value">${documentData.purchase_price ? parseFloat(documentData.purchase_price).toFixed(2) : '[Amount]'}</span></td>
                    </tr>
                    <tr>
                        <td style="padding: 5px 0;"><strong>Deposit Amount:</strong></td>
                        <td>$<span class="ldg-field-value">${documentData.purchase_deposit ? parseFloat(documentData.purchase_deposit).toFixed(2) : '[Amount]'}</span></td>
                    </tr>
                    <tr>
                        <td style="padding: 5px 0;"><strong>Closing Date:</strong></td>
                        <td><span class="ldg-field-value">${documentData.closing_date || '[Date]'}</span></td>
                    </tr>
                </table>
            </div>
            
            ${generateSignatureSection()}
        `;
    }
    
    function ldgRefreshPreview() {
        ldgUpdatePreview();
        // Visual feedback
        document.getElementById('ldg-preview').style.opacity = '0.5';
        setTimeout(function() {
            document.getElementById('ldg-preview').style.opacity = '1';
        }, 300);
    }

    function ldgDownloadPDF() {
        // Hide Elementor UI elements before printing
        jQuery('.elementor-element-overlay, .elementor-editor-element-settings, .elementor-add-section, #elementor-panel, .elementor-navigator').hide();
        
        // Add print-specific class
        jQuery('body').addClass('ldg-printing');
        
        // Clean up any Elementor classes from our container
        jQuery('#legal-doc-generator').removeClass('elementor-element elementor-widget');
        
        // Trigger print
        window.print();
        
        // Remove print class and restore after a delay
        setTimeout(function() {
            jQuery('body').removeClass('ldg-printing');
            jQuery('.elementor-element-overlay, .elementor-editor-element-settings, .elementor-add-section, #elementor-panel, .elementor-navigator').show();
        }, 1000);
    }
    </script>
    <?php
    return ob_get_clean();
}

// Add settings page
add_action('admin_menu', 'ldg_add_settings_page');
function ldg_add_settings_page() {
    add_menu_page(
        'Legal Documents',
        'Legal Documents',
        'manage_options',
        'legal-documents',
        'ldg_settings_page',
        'dashicons-media-document',
        30
    );
}

// Add body class to help with styling
add_filter('body_class', function($classes) {
    global $post;
    if (is_singular() && $post && has_shortcode($post->post_content, 'legal_documents')) {
        $classes[] = 'has-legal-documents';
    }
    return $classes;
});

// Add inline CSS to hide Elementor elements on pages with our shortcode
add_action('wp_head', function() {
    global $post;
    if (is_singular() && $post && has_shortcode($post->post_content, 'legal_documents')) {
        ?>
        <style>
        /* Hide Elementor UI on legal document pages */
        body.has-legal-documents .elementor-element-overlay,
        body.has-legal-documents .elementor-editor-element-settings,
        body.has-legal-documents .elementor-editor-element-remove,
        body.has-legal-documents .elementor-editor-element-edit {
            display: none !important;
        }
        
        /* Prevent Elementor from making our elements editable */
        body.has-legal-documents #legal-doc-generator {
            pointer-events: auto !important;
        }
        
        body.has-legal-documents #legal-doc-generator * {
            pointer-events: auto !important;
        }
        </style>
        <?php
    }
});

function ldg_settings_page() {
    ?>
    <div class="wrap">
        <h1>Legal Document Generator</h1>
        
        <div class="card">
            <h2>How to Use</h2>
            <p>Add this shortcode to any page or post:</p>
            <p><code>[legal_documents]</code> - Shows all templates with selection screen</p>
            
            <h3>Show Specific Templates Only:</h3>
            <p><code>[legal_documents templates="rental,employment"]</code></p>
            
            <h3>Show Single Template Directly (No Header/Selection):</h3>
            <p><code>[legal_documents template="rental"]</code> - Shows only rental agreement</p>
            <p><code>[legal_documents template="employment"]</code> - Shows only employment contract</p>
            <p><code>[legal_documents template="nda"]</code> - Shows only NDA</p>
            <p><code>[legal_documents template="service"]</code> - Shows only service agreement</p>
            
            <h3>Available Templates:</h3>
            <ul>
                <li><strong>rental</strong> - Rental Agreement</li>
                <li><strong>employment</strong> - Employment Contract</li>
                <li><strong>nda</strong> - Non-Disclosure Agreement</li>
                <li><strong>service</strong> - Service Agreement</li>
                <li><strong>loan</strong> - Loan Agreement</li>
                <li><strong>purchase</strong> - Purchase Agreement</li>
            </ul>
            
            <h3>Examples:</h3>
            <p><strong>Full generator with all templates:</strong><br>
            <code>[legal_documents]</code></p>
            
            <p><strong>Single template page (clean, no header):</strong><br>
            <code>[legal_documents template="rental"]</code></p>
            
            <p><strong>Multiple templates to choose from:</strong><br>
            <code>[legal_documents templates="rental,lease,sublease"]</code></p>
        </div>
    </div>
    <?php
}