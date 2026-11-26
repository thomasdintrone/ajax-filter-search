#!/usr/bin/env bash
# Repository guards for the project's hard rules. Exit 1 on any violation.
#
#   bin/check-guards.sh [base-ref]        default base: origin/master (falls back to master)
#   AFS_APPROVED_PROTECTED=1 bin/check-guards.sh   # the owner explicitly approved touching protected text
#
# 1. Protected text: the plugin header block, LICENSE, copyright lines, readme.txt and .wordpress-org/ may only
#    change with explicit approval (CI maps the PR label "approved-protected" to AFS_APPROVED_PROTECTED=1).
# 2. Free plugin must stay free: no license checks, Pro gates or outbound HTTP code in shipped files.
set -u
cd "$(dirname "$0")/.." || exit 1
base="${1:-origin/master}"
git rev-parse -q --verify "$base" >/dev/null || base=master
fail=0; bad() { echo "FAIL  $1"; fail=1; }

# --- 1. protected paths (working tree and commits versus the base) ---
changed="$(git diff --name-only "$base" --; git ls-files --others --exclude-standard)"
prot_files="$(echo "$changed" | grep -E '^(LICENSE|readme\.txt|\.wordpress-org/)' || true)"
header_of() { sed -n '1,/^ \*\/$/p' | head -40; }
if git show "$base:ajax-filter-search.php" >/dev/null 2>&1; then
	base_hdr="$(git show "$base:ajax-filter-search.php" | header_of)"
	head_hdr="$(header_of < ajax-filter-search.php)"
	[ "$base_hdr" != "$head_hdr" ] && prot_files="$prot_files"$'\n'"ajax-filter-search.php (plugin header block)"
fi
# Only plugin source is scanned (not this script, docs, tests or snapshots, which legitimately mention the word).
copyright_changes="$(git diff -U0 "$base" -- ajax-filter-search.php uninstall.php includes admin afs-template blocks public languages | grep -iE '^[+-].*(copyright|drive media)' | grep -vE '^(\+\+\+|---)' || true)"
[ -n "$copyright_changes" ] && prot_files="$prot_files"$'\n'"copyright lines:"$'\n'"$copyright_changes"
prot_files="$(echo "$prot_files" | sed '/^$/d')"
if [ -n "$prot_files" ]; then
	if [ "${AFS_APPROVED_PROTECTED:-}" = "1" ]; then
		echo "note  protected text changed (explicitly approved):"; echo "$prot_files" | sed 's/^/        /'
	else
		bad "protected text changed without approval:"; echo "$prot_files" | sed 's/^/        /'
	fi
fi

# --- 2. no Pro gates, license checks or outbound HTTP in shipped code ---
shipped="ajax-filter-search.php uninstall.php includes admin afs-template blocks public"
pattern='is_pro[a-z_]*\(|license[_ -]?(key|server|check)|edd_sl|freemius|fs_dynamic_init|wp_remote_(get|post|request|head)|curl_init|fsockopen|file_get_contents\(\s*[\x27"]https?:|wp_safe_remote|download_url\('
hits="$(grep -rniE "$pattern" $shipped 2>/dev/null | grep -v '^includes/compat.php.*Pro' || true)"
[ -n "$hits" ] && { bad "Pro/license/outbound-HTTP pattern in shipped code:"; echo "$hits" | sed 's/^/        /'; }

# --- 3. WordPress.org limits: short description at most 150 characters, in readme.txt and the plugin header ---
short_readme="$(awk '/^License URI:/{f=1;next} f&&NF{print;exit}' readme.txt)"
short_header="$(sed -n 's/^ \* Description: *//p' ajax-filter-search.php | head -1)"
[ "${#short_readme}" -gt 150 ] && bad "readme.txt short description is ${#short_readme} characters (WordPress.org limit 150)"
[ "${#short_header}" -gt 150 ] && bad "plugin header Description is ${#short_header} characters (keep at most 150)"

[ $fail -eq 0 ] && echo "check-guards: OK (base $base)"
exit $fail
