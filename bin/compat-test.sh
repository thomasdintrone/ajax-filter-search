#!/usr/bin/env bash
# Dev only: 1.x compatibility and security checks against the seeded wp-env site.
# Usage: bin/compat-test.sh [base-url]
B="${1:-http://localhost:8890}"
cd "$(dirname "$0")/.."
fail=0
check() { if [ "$2" == "$3" ]; then echo "ok    $1"; else echo "FAIL  $1 (expected '$2', got '$3')"; fail=1; fi; }
wp() { npx @wordpress/env run cli wp "$@" 2>&1 | sed 's/✔ Ran .*//' | grep -v '^ℹ\|^$'; }
items() { grep -o '<article class="[^"]*afs-item' | wc -l | tr -d ' '; }
legacy() { curl -s -d "action=my_action&$1" "$B/wp-admin/admin-ajax.php"; }
rest()   { curl -s "$B/wp-json/afs/v1/results?$1" | python3 -c 'import sys,json;print(json.load(sys.stdin)["html"])'; }

wp eval 'file_put_contents( WP_CONTENT_DIR . "/debug.log", "" );' >/dev/null

echo "-- legacy ajax action (my_action), 1.x request shapes"
check "my_action default (no page field) lists a page of 10"  10 "$(legacy 'filingType=&filterBy=&filterMonths=&filterYears=' | items)"
check "my_action page=2 works"                           10 "$(legacy 'filingType=all&page=2' | items)"
check "my_action category slug matches REST"             "$(rest 'afs_term=news&posts_per_page=10' | items)" "$(legacy 'filingType=news' | items)"
check "my_action comma list (restricted All tab)"        "$(rest 'terms=news,events&posts_per_page=10' | items)" "$(legacy 'filingType=news,events' | items)"
check "my_action keyword + year filter"                  "$(rest 'afs_s=Quarterly&afs_year=2024&posts_per_page=10' | items)" "$(legacy 'filterBy=Quarterly&filterYears=2024' | items)"
check "my_action sends deprecation header"               1 "$(curl -s -D - -o /dev/null -d 'action=my_action' "$B/wp-admin/admin-ajax.php" | grep -ci '^x-afs-deprecated')"

echo "-- security of the legacy action"
for f in filterBy filingType filterMonths filterYears page; do
  check "no shortcode execution via $f"                  0 "$(legacy "$f=x%22%5D%5Bcaption%5DINJECTED-X%5B/caption%5D%5Bafs_feed+a%3D%22" | grep -c 'INJECTED-X')"
done
check "unpublished never listed (drafts, scheduled, private)" 0 "$(legacy 'filterBy=SECRET' | items)"
check "script text is not echoed unescaped"             0 "$(legacy 'filterBy=%3Cscript%3Ealert(1)%3C/script%3E' | grep -c '<script>alert')"
check "array-typed parameters do not crash"              0 "$(curl -s -d 'action=my_action&filingType[]=x&filterBy[]=y&page[]=1' "$B/wp-admin/admin-ajax.php" | grep -c 'critical error')"
check "huge page number does not crash"                  0 "$(legacy 'page=99999999999999999999' | grep -c 'critical error')"
check "negative / garbage values do not crash"           0 "$(legacy 'page=-5&filterMonths=abc&filterYears=99999' | grep -c 'critical error')"
check "100k-character keyword does not crash"            0 "$(python3 -c "print('filterBy='+'A'*100000)" | curl -s -d @- -d 'action=my_action' "$B/wp-admin/admin-ajax.php" | grep -c 'critical error')"
check "REST rejects absurd page"                         400 "$(curl -s -o /dev/null -w '%{http_code}' "$B/wp-json/afs/v1/results?afs_page=99999999")"
check "REST never lists unpublished"                     0 "$(rest 'afs_s=SECRET' | items)"
check "admin-ajax get_selected is gone for visitors"     0 "$(curl -s -d 'action=get_selected&option=post' "$B/wp-admin/admin-ajax.php" | grep -c option)"

echo "-- 1.x PHP interfaces"
chk() { check "$1" "$2" "$(wp eval "$3" | tail -1)"; }
chk "afs_feed() exists and renders"             5 'echo substr_count( afs_feed( array( "posts_per_page" => 5 ) ), "afs-item" ) ? 5 : 0;'
chk "ajax_filter_search() exists and renders"   1 'echo false !== strpos( ajax_filter_search( array() ), "afs-wrapper" ) ? 1 : 0;'
chk "AFSinit() / AFSSettings / global \$afs"     1 'global $afs; echo ( AFSinit() instanceof AFSSettings && $afs instanceof AFSSettings ) ? 1 : 0;'
chk "constants AFS_RELEASE, AFS_CALLBACK, AFS_PLUGIN_ADMIN_URL" 3 'echo (int) defined("AFS_RELEASE") + (int) defined("AFS_CALLBACK") + (int) defined("AFS_PLUGIN_ADMIN_URL");'
chk "AFSAdmin::afs_retrieve() still works"      category 'echo AFSAdmin::afs_retrieve( "_general_post_taxonomy" );'
chk "get_the_selected_excerpt() and return_template() exist" 2 'echo (int) function_exists("get_the_selected_excerpt") + (int) function_exists("return_template");'
chk "old handles registered (afs-script-js, afs-fontawesome)" 2 'echo (int) wp_script_is("afs-script-js","registered") + (int) wp_style_is("afs-fontawesome","registered");'

echo "-- legacy markup hooks and globals"
check "first feed keeps 1.x IDs (newsForm, newsPanelResults, updateBtn, resetBtn)" 4 "$(curl -s "$B/afs-double/" | grep -o 'id="\(newsForm\|newsPanelResults\|updateBtn\|resetBtn\)"' | wc -l | tr -d ' ')"
check "second feed does not duplicate them"             1 "$(curl -s "$B/afs-double/" | grep -o 'id="newsForm"' | wc -l | tr -d ' ')"
check "ajaxurl/scriptpath defined on feed pages"        1 "$(curl -s "$B/afs-single/" | grep -c 'window.ajaxurl')"
check "ajaxurl/scriptpath NOT defined on other pages"   0 "$(curl -s "$B/sample-page/" | grep -c 'window.ajaxurl')"

echo "-- compatibility switch off"
wp eval 'wp_mkdir_p( WPMU_PLUGIN_DIR ); file_put_contents( WPMU_PLUGIN_DIR . "/afs-off.php", "<?php add_filter( \"afs_enable_legacy_compat\", \"__return_false\" );" );' >/dev/null
check "off: my_action answers 0"                        0 "$(legacy 'filingType=' | tr -d '\n')"
check "off: no legacy IDs"                              0 "$(curl -s "$B/afs-single/" | grep -c 'id="newsForm"')"
check "off: no legacy globals"                          0 "$(curl -s "$B/afs-single/" | grep -c 'window.ajaxurl')"
wp eval 'unlink( WPMU_PLUGIN_DIR . "/afs-off.php" );' >/dev/null

echo "-- theme template override written for 1.x"
git show v1.0.3:afs-template/default.php > bin/_old-default.php
wp eval '$t = get_stylesheet_directory() . "/afs-template"; wp_mkdir_p( $t ); copy( WP_PLUGIN_DIR . "/ajax-filter-search/bin/_old-default.php", $t . "/default.php" );' >/dev/null
check "1.0.3 default.php used as override still renders" 10 "$(curl -s "$B/afs-single/" | items)"
check "override markup is the old markup"               10 "$(curl -s "$B/afs-single/" | grep -c 'afs-GaLabel' | sed 's/^[1-9][0-9]*$/10/')"
wp eval 'global $wp_filesystem; $t = get_stylesheet_directory() . "/afs-template"; @unlink( $t . "/default.php" ); @rmdir( $t );' >/dev/null
rm -f bin/_old-default.php

echo "-- PHP log"
log="$(wp eval 'echo file_exists( WP_CONTENT_DIR . "/debug.log" ) ? trim( file_get_contents( WP_CONTENT_DIR . "/debug.log" ) ) : "";')"
check "no PHP warnings, notices or errors logged"       "" "$log"; [ -n "$log" ] && echo "$log" | head -5
exit $fail
