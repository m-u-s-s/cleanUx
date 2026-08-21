#!/bin/bash
# PARCOURS À LA MAIN DE L'APPLICATION NATIVE.
#
# On tape, on attend, on capture, et on lit ce que le moteur JavaScript a craché
# pendant ce temps. `logcat` est la console du natif : c'est là que les erreurs
# React Native apparaissent, et nulle part ailleurs.
#
#   parcours-natif.sh "Accueil:168:2842" "Explorer:503:2842" ...

export PATH="$PATH:/c/Users/mmdar/AppData/Local/Android/Sdk/platform-tools"
APPAREIL="${APPAREIL:-emulator-5554}"
SORTIE="${SORTIE:-tools/visual-qa/out/natif}"
PAUSE="${PAUSE:-3}"

# Ce qui est du bruit d'environnement, pas un défaut de l'application.
BRUIT='expo-notifications|Notifications désactivées|WebSocket|pusher|ExpoGo|Unable to resolve host'

mkdir -p "$SORTIE"
total=0 ; incidents=0

for cible in "$@"; do
  nom="${cible%%:*}" ; reste="${cible#*:}" ; x="${reste%%:*}" ; y="${reste##*:}"
  total=$((total+1))

  adb -s "$APPAREIL" logcat -c 2>/dev/null
  adb -s "$APPAREIL" shell input tap "$x" "$y" 2>/dev/null
  sleep "$PAUSE"

  adb -s "$APPAREIL" exec-out screencap -p > "$SORTIE/$nom.png" 2>/dev/null

  erreurs=$(adb -s "$APPAREIL" logcat -d -s ReactNativeJS:E ReactNativeJS:W AndroidRuntime:E 2>/dev/null \
    | grep -aiE "error|exception|undefined is not|cannot read|failed" \
    | grep -avE "$BRUIT" | head -3)

  if [ -n "$erreurs" ]; then
    incidents=$((incidents+1))
    printf "  %-22s ✗\n" "$nom"
    echo "$erreurs" | sed 's/^/       /'
  else
    printf "  %-22s ·\n" "$nom"
  fi
done

echo "  ---"
echo "  $total écran(s) visité(s), $incidents avec incident"
