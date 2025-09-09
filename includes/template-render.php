<?php
if (!defined('ABSPATH')) { exit; }

function bimi_checker_render_form() { ?>
    <div class="bimichecker">
        <form class="bimichecker-form" id="bimichecker-form">
            <div class="bimichecker-field">
                <label for="bimi-domain">Domain</label>
                <input type="text" id="bimi-domain" name="domain" placeholder="example.com" required />
            </div>
            <div class="bimichecker-field">
                <label for="bimi-selector">Selector</label>
                <input type="text" id="bimi-selector" name="selector" placeholder="default" />
            </div>
            <button type="submit" class="bimichecker-btn">Check BIMI</button>
        </form>

        <div id="bimichecker-results" class="bimichecker-results" hidden>
            <div class="bimichecker-summary" id="bimichecker-summary"></div>
            <div class="bimichecker-grid">
                <div class="bimichecker-card">
                    <h3>BIMI</h3>
                    <ul id="bimi-status" class="status-list"></ul>
                </div>
                <div class="bimichecker-card">
                    <h3>DMARC</h3>
                    <ul id="dmarc-status" class="status-list"></ul>
                </div>
                <div class="bimichecker-card">
                    <h3>Preview</h3>
                    <div class="mock-client">
                        <div class="mock-body two-col">
                            <div class="mock-logo" id="mock-logo" aria-label="Brand logo"></div>
                            <div class="mock-right">
                                <div class="mock-header">
                                    <div class="mock-from" id="mock-from">Brand &lt;email@example.com&gt;</div>
                                    <div class="mock-subject" id="mock-subject">Subject: Example subject for example.com</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php }
