<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/** @var string $domain */
/** @var string $selector */
/** @var array|null $result */
?>
<div class="bimichecker">
  <form class="bimichecker-form" method="get">
    <div class="bimichecker-field">
      <label for="bimi_domain">Domain</label>
      <input id="bimi_domain" name="bimi_domain" type="text" value="<?php echo esc_attr( $domain ); ?>" placeholder="example.com" />
    </div>
    <div class="bimichecker-field">
      <label for="bimi_selector">Selector (optional)</label>
      <input id="bimi_selector" name="bimi_selector" type="text" value="<?php echo esc_attr( $selector ?: 'default' ); ?>" placeholder="default" />
    </div>
    <button type="submit" class="bimichecker-btn">Check</button>
  </form>

  <?php if ( isset($result) && $result !== null ) : ?>
    <div class="bimichecker-results">

      <?php if ( is_array($result) && ! empty($result['error']) ) : ?>
        <div class="alert error"><strong>Lookup error:</strong> <?php echo esc_html( $result['error'] ); ?></div>
      <?php else : ?>

        <!-- BIMI block -->
        <div class="bimichecker-card">
          <h3>BIMI</h3>
          <?php
            $first = $result['bimi_first_kv'] ?? [];
            $raw_bimi = '';
            if ( ! empty($result['txt_raw']) ) {
              // txt_raw now contains only records that passed BIMI version filtering.
              $raw_bimi = is_array($result['txt_raw']) ? (string) reset($result['txt_raw']) : (string) $result['txt_raw'];
            }

            // AVP colour logic: brand|personal => ok, '' => warn, else => error.
            $avp_val_raw = isset($first['avp']) ? trim(strtolower((string)$first['avp'])) : '';
            if ($avp_val_raw === 'brand' || $avp_val_raw === 'personal') {
              $avp_class = 'status-ok';
              $avp_icon  = 'ic ic-ok';
            } elseif ($avp_val_raw === '') {
              $avp_class = 'status-warn';
              $avp_icon  = 'ic ic-warn';
            } else {
              $avp_class = 'status-error';
              $avp_icon  = 'ic ic-err';
            }

            $uri_validation = $result['uri_validation'] ?? [];
            $a_validation   = $uri_validation['a'] ?? ['status' => 'warn', 'message' => ''];
            $l_validation   = $uri_validation['l'] ?? ['status' => 'warn', 'message' => ''];

            $status_class = static function($status) {
              if ($status === 'ok') { return 'status-ok'; }
              if ($status === 'error') { return 'status-error'; }
              return 'status-warn';
            };
            $status_icon = static function($status) {
              if ($status === 'ok') { return 'ic ic-ok'; }
              if ($status === 'error') { return 'ic ic-err'; }
              return 'ic ic-warn';
            };

            $lps = $result['lps'] ?? ['present' => false, 'value' => null, 'valid' => null, 'prefixes' => [], 'message' => ''];
            ?>
            <div class="bimichecker-card">
            <h3>BIMI</h3>
            <ul class="status-list">
                <li class="status-item <?php echo !empty($result['bimi_found']) ? 'status-ok' : 'status-warn'; ?>">
                <span class="<?php echo !empty($result['bimi_found']) ? 'ic ic-ok' : 'ic ic-warn'; ?>" aria-hidden="true"></span>
                <span class="label">BIMI record found</span>
                <span class="detail">
                    <?php if ( !empty($result['bimi_found']) ) : ?>
                    <code><?php echo esc_html( $raw_bimi ); ?></code>
                    <?php else : ?>
                    <code>false</code>
                    <?php endif; ?>
                </span>
                </li>

                <?php if ( ! empty($result['fallback_used']) ) : ?>
                <li class="status-item status-ok">
                <span class="ic ic-ok" aria-hidden="true"></span>
                <span class="label">Organizational Domain fallback</span>
                <span class="detail"><code><?php echo esc_html( $result['label'] ?? '' ); ?></code></span>
                </li>
                <?php endif; ?>

                <li class="status-item <?php echo esc_attr( $status_class($a_validation['status'] ?? 'warn') ); ?>">
                <span class="<?php echo esc_attr( $status_icon($a_validation['status'] ?? 'warn') ); ?>" aria-hidden="true"></span>
                <span class="label">a= (VMC URL)</span>
                <span class="detail">
                    <?php if ( ! empty( $first['a'] ) ) : ?>
                    <a href="<?php echo esc_url( $first['a'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $first['a'] ); ?></a>
                    <?php else : ?>
                    <code>—</code>
                    <?php endif; ?>
                    <?php if ( ! empty($a_validation['message']) ) : ?><br><?php echo esc_html( $a_validation['message'] ); ?><?php endif; ?>
                </span>
                </li>

                <li class="status-item <?php echo esc_attr( $status_class($l_validation['status'] ?? 'warn') ); ?>">
                <span class="<?php echo esc_attr( $status_icon($l_validation['status'] ?? 'warn') ); ?>" aria-hidden="true"></span>
                <span class="label">l= (Logo URL)</span>
                <span class="detail">
                    <?php if ( ! empty( $first['l'] ) ) : ?>
                    <a href="<?php echo esc_url( $first['l'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $first['l'] ); ?></a>
                    <?php else : ?>
                    <code>—</code>
                    <?php endif; ?>
                    <?php if ( ! empty($l_validation['message']) ) : ?><br><?php echo esc_html( $l_validation['message'] ); ?><?php endif; ?>
                </span>
                </li>

                <li class="status-item <?php echo $avp_class; ?>">
                <span class="<?php echo $avp_icon; ?>" aria-hidden="true"></span>
                <span class="label">avp=</span>
                <span class="detail">
                    <code><?php echo ($avp_val_raw !== '') ? esc_html($avp_val_raw) : '—'; ?></code>
                </span>
                </li>
                
                <li class="status-item <?php echo !empty($lps['present']) ? 'status-ok' : 'status-warn'; ?>">
                <span class="<?php echo !empty($lps['present']) ? 'ic ic-ok' : 'ic ic-warn'; ?>" aria-hidden="true"></span>
                <span class="label">lps=</span>
                <span class="detail">
                    <code><?php echo !empty($lps['present']) ? esc_html($lps['value']) : '—'; ?></code>
                </span>
                </li>

                <?php if ( ! empty($lps['present']) ) : ?>
                <li class="status-item <?php echo !empty($lps['valid']) ? 'status-ok' : 'status-error'; ?>">
                <span class="<?php echo !empty($lps['valid']) ? 'ic ic-ok' : 'ic ic-err'; ?>" aria-hidden="true"></span>
                <span class="label">lps= (Local-part Selector)</span>
                <span class="detail">
                    <code><?php echo ($lps['value'] === '') ? '(empty)' : esc_html( $lps['value'] ); ?></code>
                    <?php if ( ! empty($lps['message']) ) : ?><br><?php echo esc_html( $lps['message'] ); ?><?php endif; ?>
                </span>
                </li>
                <?php endif; ?>
            </ul>
            </div>

        <!-- DMARC block (colour coded with sp/pct logic) -->
        <div class="bimichecker-card">
        <h3>DMARC</h3>
        <?php $d = $result['dmarc'] ?? null; ?>
        <?php if ( is_array($d) && ! empty($d['ok']) ) : ?>
            <?php
            $p        = strtolower( $d['parsed']['p']  ?? '' );
            $sp       = strtolower( $d['parsed']['sp'] ?? '' );
            $pct_pub  = $d['present']['pct'] ?? false;          // was pct explicitly published?
            $pct_eff  = $d['effective']['pct'] ?? '100';        // 100 if not published
            $rua      = $d['parsed']['rua'] ?? '';

            $p_ok     = in_array($p, ['quarantine','reject'], true);

            // sp logic:
            // - If NOT published: hide the row entirely.
            // - If published: must be quarantine/reject to pass; else warn.
            $sp_published = $d['present']['sp'] ?? false;
            $sp_ok        = $sp_published ? in_array($sp, ['quarantine','reject'], true) : null; // null means "no row"

            // pct logic:
            // - OK if effective pct == 100 (either not published or published as 100)
            // - WARN if explicitly published and != 100
            $pct_ok = ($pct_eff === '100');
            $pct_warn_due_to_publish = ($pct_pub && $pct_eff !== '100');

            $rua_ok   = $rua !== '';
            ?>
            <ul class="status-list">
            <li class="status-item status-ok">
                <span class="ic ic-ok" aria-hidden="true"></span>
                <span class="label">DMARC record found</span>
                <span class="detail"><code>true</code></span>
            </li>

            <li class="status-item <?php echo $p_ok ? 'status-ok' : 'status-warn'; ?>">
                <span class="<?php echo $p_ok ? 'ic ic-ok' : 'ic ic-warn'; ?>" aria-hidden="true"></span>
                <span class="label">p= (domain policy)</span>
                <span class="detail"><code><?php echo esc_html( $d['parsed']['p'] ?? '—' ); ?></code></span>
            </li>

            <?php if ( $sp_published ) : ?>
                <li class="status-item <?php echo $sp_ok ? 'status-ok' : 'status-warn'; ?>">
                <span class="<?php echo $sp_ok ? 'ic ic-ok' : 'ic ic-warn'; ?>" aria-hidden="true"></span>
                <span class="label">sp= (subdomain policy)</span>
                <span class="detail"><code><?php echo esc_html( $d['parsed']['sp'] ?? '—' ); ?></code></span>
                </li>
            <?php endif; ?>

            <li class="status-item <?php echo $pct_ok ? 'status-ok' : 'status-warn'; ?>">
                <span class="<?php echo $pct_ok ? 'ic ic-ok' : 'ic ic-warn'; ?>" aria-hidden="true"></span>
                <span class="label">pct=</span>
                <span class="detail">
                <code><?php echo esc_html( $pct_eff ); ?></code>
                <?php if ( $pct_warn_due_to_publish ) : ?>
                    <!-- Optional hint: explicitly published non-100 -->
                <?php endif; ?>
                </span>
            </li>

            <li class="status-item <?php echo $rua_ok ? 'status-ok' : 'status-warn'; ?>">
                <span class="<?php echo $rua_ok ? 'ic ic-ok' : 'ic ic-warn'; ?>" aria-hidden="true"></span>
                <span class="label">rua=</span>
                <span class="detail"><code><?php echo $rua_ok ? esc_html( $rua ) : '—'; ?></code></span>
            </li>
            </ul>
        <?php else : ?>
            <ul class="status-list">
            <li class="status-item status-warn">
                <span class="ic ic-warn" aria-hidden="true"></span>
                <span class="label">DMARC record found</span>
                <span class="detail"><code>false</code></span>
            </li>
            </ul>
        <?php endif; ?>
        </div>

        <!-- Preview: circular logo + "[domain] newsletter" -->
        <div class="bimichecker-card">
          <h3>Preview</h3>
          <div class="mock-client">
            <div class="mock-body two-col">
              <?php $logo = $first['l'] ?? ''; ?>
              <div class="mock-logo" style="<?php echo $logo ? 'background-image:url(' . esc_url( $logo ) . ');' : ''; ?>"></div>
              <div class="mock-right">
                <div class="mock-header">
                  <div class="mock-from">From: <?php echo esc_html( $domain ); ?> &lt;news@<?php echo esc_html( $domain ); ?>&gt;</div>
                  <div class="mock-subject"><?php echo esc_html( $domain ); ?> newsletter</div>
                </div>
              </div>
            </div>
          </div>
        </div>

      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>
