#!/usr/bin/env python3
"""Generate user_files/announcement_voices.json from Piper voices.json.

Source: https://huggingface.co/rhasspy/piper-voices/raw/main/voices.json

Usage:
  python3 scripts/generate-announcement-voices.py [/path/to/piper-voices.json]
  curl -sS -o /tmp/piper-voices.json https://huggingface.co/rhasspy/piper-voices/raw/main/voices.json
  python3 scripts/generate-announcement-voices.py /tmp/piper-voices.json
"""

from __future__ import annotations

import json
import sys
import urllib.request
from pathlib import Path

REPO_ROOT = Path(__file__).resolve().parents[1]
OUT_PATH = REPO_ROOT / "user_files" / "announcement_voices.json"
PIPER_URL = "https://huggingface.co/rhasspy/piper-voices/raw/main/voices.json"

REGIONS = [
    "Americas",
    "Europe",
    "Asia-Pacific",
    "Middle East & Africa",
    "Other",
]

# Curated defaults shown before "Show all" in each region.
CURATED: dict[str, list[str]] = {
    "Americas": [
        "en_US-amy-low",
        "en_US-lessac-medium",
        "en_US-joe-medium",
        "en_US-kristin-medium",
        "en_US-ryan-low",
        "es_MX-claude-high",
        "es_MX-ald-medium",
        "pt_BR-faber-medium",
        "es_AR-daniela-high",
    ],
    "Europe": [
        "en_GB-alan-low",
        "en_GB-jenny_dioco-medium",
        "en_GB-southern_english_female-low",
        "de_DE-thorsten-medium",
        "fr_FR-siwis-medium",
        "es_ES-davefx-medium",
        "it_IT-paola-medium",
        "pl_PL-gosia-medium",
        "nl_NL-mls-medium",
        "sv_SE-nst-medium",
    ],
    "Asia-Pacific": [
        "zh_CN-huayan-medium",
        "hi_IN-pratham-medium",
        "vi_VN-vais1000-medium",
        "id_ID-news_tts-medium",
        "te_IN-padmavathi-medium",
        "ml_IN-meera-medium",
        "ne_NP-google-medium",
    ],
    "Middle East & Africa": [
        "ar_JO-kareem-medium",
        "fa_IR-ganji-medium",
        "tr_TR-dfki-medium",
        "sw_CD-lanfrica-medium",
    ],
    "Other": [
        "uk_UA-ukrainian_tts-medium",
        "ru_RU-denis-medium",
        "cs_CZ-jirka-medium",
        "el_GR-rapunzelina-medium",
        "ca_ES-upc_ona-medium",
    ],
}

AMERICAS = {
    "US", "CA", "MX", "BR", "AR", "CL", "CO", "PE", "VE", "EC", "UY", "PY", "BO",
    "CR", "PA", "HN", "GT", "SV", "NI", "DO", "CU", "PR", "JM", "TT", "BZ", "SR",
    "GY", "HT", "BB", "LC", "VC", "GD", "AG", "KN", "DM", "AW", "CW", "SX", "BQ",
}
EUROPE = {
    "GB", "IE", "FR", "DE", "IT", "ES", "PT", "NL", "BE", "CH", "AT", "PL", "CZ",
    "SK", "HU", "RO", "BG", "GR", "FI", "SE", "NO", "DK", "IS", "UA", "RU", "LU",
    "LV", "SI", "RS", "AL", "CY", "MT", "EE", "LT", "MD", "BY", "BA", "MK", "ME",
    "AD", "MC", "SM", "VA", "LI", "FO", "GI", "IM", "JE", "GG", "EU",
}
ASIA_PACIFIC = {
    "CN", "TW", "HK", "MO", "JP", "KR", "IN", "TH", "VN", "ID", "MY", "PH", "SG",
    "AU", "NZ", "NP", "PK", "BD", "LK", "MM", "KH", "LA", "MN", "KZ", "KG", "UZ",
    "TJ", "TM", "AF", "BN", "TL", "FJ", "PG", "NC", "PF", "WS", "TO", "VU", "SB",
    "KI", "TV", "NR", "PW", "FM", "MH", "CK", "NU", "TK",
}
MIDDLE_EAST_AFRICA = {
    "JO", "SA", "IR", "IQ", "SY", "LB", "IL", "PS", "YE", "OM", "AE", "QA", "BH",
    "KW", "TR", "EG", "LY", "TN", "DZ", "MA", "SD", "SS", "ET", "ER", "DJ", "SO",
    "KE", "UG", "TZ", "RW", "BI", "CD", "CG", "GA", "CM", "CF", "TD", "NE", "NG",
    "BJ", "TG", "GH", "CI", "LR", "SL", "GN", "GW", "SN", "GM", "ML", "BF", "MR",
    "CV", "ST", "GQ", "AO", "ZM", "ZW", "MW", "MZ", "MG", "MU", "SC", "KM", "YT",
    "RE", "SH", "EH", "ZA", "NA", "BW", "LS", "SZ",
}


def region_for_locale(locale: str) -> str:
    if "_" not in locale:
        return "Other"
    country = locale.split("_", 1)[1]
    if country in AMERICAS:
        return "Americas"
    if country in EUROPE:
        return "Europe"
    if country in ASIA_PACIFIC:
        return "Asia-Pacific"
    if country in MIDDLE_EAST_AFRICA:
        return "Middle East & Africa"
    return "Other"


def onnx_path(meta: dict) -> str | None:
    for path in meta.get("files", {}):
        if path.endswith(".onnx"):
            return path.rsplit("/", 1)[0]
    return None


def speaker_label(meta: dict) -> str:
    lang = meta.get("language", {})
    name = str(meta.get("name", "")).replace("_", " ").title()
    language = lang.get("name_english", "Unknown")
    country = lang.get("country_english", "")
    quality = meta.get("quality", "")
    place = f"{language} ({country})" if country else language
    return f"{name} — {place}, {quality}"


def load_piper_voices(path: Path | None) -> dict:
    if path and path.is_file():
        return json.loads(path.read_text(encoding="utf-8"))
    with urllib.request.urlopen(PIPER_URL, timeout=60) as resp:
        return json.loads(resp.read().decode("utf-8"))


def main() -> int:
    src = Path(sys.argv[1]) if len(sys.argv) > 1 else None
    piper = load_piper_voices(src)

    curated_ids: set[str] = set()
    for ids in CURATED.values():
        curated_ids.update(ids)

    missing_curated = sorted(curated_ids - set(piper))
    if missing_curated:
        print("Warning: curated voice ids not in Piper catalog:", ", ".join(missing_curated), file=sys.stderr)

    voices_out: dict[str, dict] = {}
    for voice_id, meta in sorted(piper.items()):
        hf_parent = onnx_path(meta)
        if hf_parent is None:
            continue

        locale = str(meta.get("language", {}).get("code", ""))
        region = region_for_locale(locale)
        voices_out[voice_id] = {
            "label": speaker_label(meta),
            "huggingface_path": hf_parent,
            "region": region,
            "language": str(meta.get("language", {}).get("name_english", "")),
            "locale": locale,
            "quality": str(meta.get("quality", "")),
            "curated": voice_id in CURATED.get(region, []),
        }

    payload = {
        "catalog_version": "piper-voices",
        "source": PIPER_URL,
        "regions": REGIONS,
        "voices": voices_out,
    }

    OUT_PATH.write_text(json.dumps(payload, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")
    print(f"Wrote {len(voices_out)} voices to {OUT_PATH}")
    for region in REGIONS:
        total = sum(1 for v in voices_out.values() if v["region"] == region)
        picked = sum(1 for v in voices_out.values() if v["region"] == region and v["curated"])
        print(f"  {region}: {picked} curated / {total} total")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
