# Announcements (V4.3.0+)

Upload or generate audio, play it on a node (local or global), and optionally schedule playback with cron. The dashboard **Announcements** button opens a modal with **Playback**, **Create**, and **Scheduled** tabs.

Requires Supermon-ng **≥ 4.3.0** installed via the **`.deb` package** or a complete upgrade (tarball `update.sh` may not deploy `announce-*.sh` or sudoers — see [DEBIAN.md](DEBIAN.md#migrating-from-tarball-installsh-to-apt)).

## Requirements

Recommended packages:

```bash
sudo apt-get install sox libsox-fmt-mp3 asl3-tts
```

These are listed as `Recommends` on the Debian package.

## Permissions

Add your login username (same as `user_files/.htpasswd` / `manage_users.php`) to arrays in `user_files/authusers.inc`:

| Array | Grants |
|-------|--------|
| `$ANNOUNCEUSER` | Announcements button; upload MP3/WAV, TTS, **local** play, delete, install Piper voices |
| `$ANNOUNCEGLOBALUSER` | **Global** playback and global schedules |
| `$ANNOUNCESCHEDUSER` | **Scheduled** tab (cron-based playback) |

**Local announcements only:**

```php
$ANNOUNCEUSER=array("youruser");
$ANNOUNCEGLOBALUSER=array();
$ANNOUNCESCHEDUSER=array();
```

**Full access for one operator:**

```php
$ANNOUNCEUSER=array("youruser");
$ANNOUNCEGLOBALUSER=array("youruser");
$ANNOUNCESCHEDUSER=array("youruser");
```

Empty arrays mean no one has that capability. Permissions load on the next login — no restart required.

Wrong permissions return **403** from the API, not a silent failure.

## Files and scripts

| Path | Purpose |
|------|---------|
| `user_files/announcements.ini` | Defaults, paths, upload limits, TTS voice (conffile on `.deb`) |
| `user_files/mp3/` | Library (`.ul` copies kept after install) |
| `/usr/local/share/asterisk/sounds/announcements/` | Asterisk playback directory |
| `user_files/sbin/announce-play.sh` | Play via `rpt localplay` / `rpt playback` |
| `user_files/sbin/announce-install.sh` | Convert upload to ulaw |
| `user_files/sbin/announce-tts.sh` | Generate TTS with `asl-tts` / Piper |
| `user_files/sbin/announce-delete.sh` | Remove library + sounds copy |
| `user_files/sbin/announce-schedule.sh` | Root crontab list/add/toggle/delete |
| `user_files/sbin/announce-voice-install.sh` | Download Piper voice on demand |

Sudoers must allow `www-data` to run these scripts (`/etc/sudoers.d/011-supermon-ng` on `.deb` installs). On upgrade, if dpkg prompts about sudoers, choose the **maintainer version** to pick up new `announce-*.sh` lines unless you have custom edits.

Package maintainers refresh the Piper voice catalog with `scripts/generate-announcement-voices.py`.

## Modal overview

**Playback** — choose a **local node** from `allmon.ini`, scope (local/global), mode (polite/priority), and a library file.

**Create** — upload MP3/WAV, or TTS (requires a node for generation context, Piper voice, and `asl-tts`).

**Scheduled** — cron jobs stored in root’s crontab via `announce-schedule.sh` (requires `$ANNOUNCESCHEDUSER`).

### Local vs global scope

Supermon does not implement its own linking logic. It runs Asterisk/app_rpt on the node you select:

| Scope | Asterisk command | Effect |
|-------|------------------|--------|
| **Local** | `rpt localplay` | Audio on the selected node only |
| **Global** | `rpt playback` | Audio sent to **AllStar links** connected on that node |

**Polite** waits for the node to be clear (`RPT_RXKEYED`) before playing, up to five minutes. **Priority** plays immediately.

The node dropdown lists **configured local nodes** from `allmon.ini`, not remote link or EchoLink session IDs.

## Global playback and EchoLink

Nothing in Supermon blocks EchoLink explicitly. Global mode calls `rpt playback` on the **local node you pick**. Whether EchoLink users hear it depends on Asterisk/app_rpt and `rpt.conf`, not the modal.

### Pick the correct local node

On servers with multiple nodes, EchoLink is usually bound to **one** hub node in `echolink.conf` / `rpt.conf`. If you run **Global** on a different RF node, AllStar links on that node may hear the announcement while EchoLink on the hub node does not.

Select the node that actually carries your EchoLink bridge.

### `echolinkdefault` in `rpt.conf`

EchoLink often does not receive playback or telemetry unless EchoLink output is enabled. Many sites use:

```ini
echolinkdefault = 1   ; 0 = off; 1 = on; 2 = timed; 3 = follow local telemetry
```

See the [AllStarLink telemetry docs](https://allstarlink.github.io/adv-topics/telemetry/) and community threads on playing audio to connected EchoLink stations. Connect announcements and file playback to EchoLink users both commonly need this on `0`-default systems.

### `duplex=0` on the originating node

If the node you play from has **`duplex=0`**, `rpt playback` may behave like **`rpt localplay`** and **not propagate to any links** (AllStar or EchoLink). This is common on nodes fronting an external repeater controller. See [app_rpt issue #773](https://github.com/AllStarLink/app_rpt/issues/773).

Check `duplex` and `linktolink` on the node you announce from. Intermediate hub nodes in a link chain also generally need `duplex` ≠ `0` for playback to reach distant endpoints.

### Monitor-only links and foreign-link gating

EchoLink or other links in monitor-only mode, or with foreign-link output disabled (COP 36–39 / telemetry settings), may not pass global playback even when local RF and transceive links do.

### Debug on the node

Replace `NNNNN` and `yourfile` with your node number and announcement basename (no extension):

```bash
# Global — should be heard on connected AllStar links (and EchoLink if rpt.conf allows)
sudo asterisk -rx "rpt playback NNNNN announcements/yourfile"

# Local — selected node only
sudo asterisk -rx "rpt localplay NNNNN announcements/yourfile"
```

Interpretation:

- **Both only local** → `duplex=0` or audio gating on that node.
- **AllStar links hear it, EchoLink does not** → wrong node selected, `echolinkdefault=0`, or EchoLink output path settings.
- **Priority vs polite** — if Polite seems to do nothing on a busy hub, try **Priority** once to rule out waiting on `RPT_RXKEYED`.

## Troubleshooting

### Scheduled tab empty or API errors

`authusers.inc` is not the cause of HTTP **500** (that is **403**). Check:

- Supermon-ng **≥ 4.3.0** with all `user_files/sbin/announce-*.sh` present and executable (`chmod 755`).
- Sudoers includes `announce-schedule.sh` for `www-data`.
- As `www-data`:  
  `sudo -n /var/www/html/supermon-ng/user_files/sbin/announce-schedule.sh list`  
  should print `[]` or JSON, not “not allowed” or “command not found”.
- App log: `logs/app-YYYY-MM-DD.log` for `Announcements sudo script failed` or `Could not list announcement schedules`.

Partial tarball upgrades (`update.sh`) often preserve an old `user_files/sbin/` tree and skip new `announce-*.sh` scripts. Migrate to the [`.deb` package](DEBIAN.md#migrating-from-tarball-installsh-to-apt) instead of re-running `update.sh`.

### TTS or upload fails

- `sox` / `libsox-fmt-mp3` for audio conversion.
- `asl3-tts` and an installed Piper voice for TTS.
- Upload size and extensions in `user_files/announcements.ini` (`max_bytes`, `allowed_extensions`).

### Play returns success but no audio

- Confirm `.ul` exists under `/usr/local/share/asterisk/sounds/announcements/`.
- Run the `rpt playback` / `rpt localplay` CLI tests above.
- Check Asterisk logs and `rpt show variables` for the node.

## API reference

All routes require login. POST/PATCH/DELETE require CSRF.

| Method | Path | Permission |
|--------|------|------------|
| GET | `/api/v1/announcements` | `$ANNOUNCEUSER` |
| GET | `/api/v1/announcements/voices` | `$ANNOUNCEUSER` |
| POST | `/api/v1/announcements/play` | `$ANNOUNCEUSER`; global needs `$ANNOUNCEGLOBALUSER` |
| POST | `/api/v1/announcements/upload` | `$ANNOUNCEUSER` |
| POST | `/api/v1/announcements/tts` | `$ANNOUNCEUSER` |
| DELETE | `/api/v1/announcements/{name}` | `$ANNOUNCEUSER` |
| GET | `/api/v1/announcements/schedules` | `$ANNOUNCESCHEDUSER` |
| POST | `/api/v1/announcements/schedules` | `$ANNOUNCESCHEDUSER`; global needs `$ANNOUNCEGLOBALUSER` |
| PATCH | `/api/v1/announcements/schedules/{id}/enabled` | `$ANNOUNCESCHEDUSER` |
| DELETE | `/api/v1/announcements/schedules/{id}` | `$ANNOUNCESCHEDUSER` |
