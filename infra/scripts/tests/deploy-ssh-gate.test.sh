#!/usr/bin/env bash
# Banc d'attaque de infra/scripts/deploy-ssh-gate.sh (aucun accès réseau ni
# VPS : racine de déploiement temporaire, ssh simulé). Nécessite rsync et
# rrsync (paquet rsync). Usage : bash infra/scripts/tests/deploy-ssh-gate.test.sh
set -uo pipefail
GATE="$(cd "$(dirname "$0")/.." && pwd)/deploy-ssh-gate.sh"
T=$(mktemp -d); trap 'rm -rf "$T"' EXIT
R=$T/root; mkdir -p "$R/infra/scripts" "$T/src/sub" "$T/secret"
echo 'echo "REMOTE-RAN $1"' > "$R/infra/scripts/deploy-remote.sh"
echo hi > "$T/src/sub/f"; echo TOPSECRET > "$T/secret/s"
export HMM_DEPLOY_ROOT=$R HMM_RRSYNC="${HMM_RRSYNC:-/usr/bin/rrsync}"
fail=0
t() { local exp=$1 cmd=$2 rc
  SSH_ORIGINAL_COMMAND="$cmd" bash "$GATE" </dev/null >/dev/null 2>&1; rc=$?
  if { [[ $exp == OK ]] && ((rc == 0)); } || { [[ $exp == KO ]] && ((rc != 0)); }; then
    echo "PASS $exp $(printf %q "$cmd")"
  else echo "FAIL $exp rc=$rc $(printf %q "$cmd")"; fail=1; fi; }
D="bash $R/infra/scripts/deploy-remote.sh"
O="rsync --server -logDtpre.iLsfxCIvu"
t OK "$D latest"
t OK "$D 8a6c6391abcdef"
for c in "" "id" "$D latest; id" "$D 'abc;id'" "$D \$(id)" "$D latest"$'\n'"id" \
  "$D abc" "$D latest extra" "bash $R/infra/../infra/scripts/deploy-remote.sh latest" \
  "bash /tmp/x/infra/scripts/deploy-remote.sh latest" "sh $R/infra/scripts/deploy-remote.sh latest" \
  "rsync --server --sender -logDtpre.iLsfxCIvu . $R/" "$O . /etc/" "$O . $R/../secret/" \
  "$O . $R/ ; id" "$O . $R/x . $R/" "rsync --server --rsh=id -logDtpre.iLsfxCIvu . $R/" \
  "scp -t $R/"; do t KO "$c"; done
# rsync réel (options de deploy.yml) : écriture + --delete OK, lecture refusée
printf '#!/usr/bin/env bash\nshift; SSH_ORIGINAL_COMMAND="$*" exec bash %q\n' "$GATE" > "$T/ssh"; chmod +x "$T/ssh"
touch "$R/stale"
if rsync -az --delete --omit-dir-times --no-perms -e "$T/ssh" "$T/src/" "x:$R/" >/dev/null 2>&1 \
   && [[ -f $R/sub/f && ! -e $R/stale ]]; then echo "PASS rsync écriture + --delete"
else echo "FAIL rsync écriture"; fail=1; fi
if rsync -az -e "$T/ssh" "x:$R/" "$T/out/" >/dev/null 2>&1; then echo "FAIL rsync lecture acceptée"; fail=1
else echo "PASS rsync lecture refusée"; fi
# --log-file / --files-from : rrsync les confine sous la racine (rien hors de $R)
SSH_ORIGINAL_COMMAND="rsync --server --log-file=$T/pwn -logDtpre.iLsfxCIvu . $R/" bash "$GATE" </dev/null >/dev/null 2>&1
[[ -e $T/pwn ]] && { echo "FAIL --log-file hors racine"; fail=1; } || echo "PASS --log-file confiné"
exit $fail
