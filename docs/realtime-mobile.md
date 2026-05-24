# Reverb / WebSocket — Mobile RN integration

## Overview

The React-Native app must NOT hard-code any WebSocket connection parameters.
Instead, it calls `GET /api/realtime/socket-config` on startup to discover the
Reverb host, port, key, and auth endpoint, then bootstraps Pusher-JS accordingly.

---

## 1. Discovery flow

```
[RN boot] → GET /api/realtime/socket-config  (Authorization: Bearer <token>)
           ← {driver, key, host, port, scheme, auth_endpoint}
```

Example response (production):

```json
{
  "driver": "reverb",
  "key": "pk_xxx",
  "host": "realtime.cleanux.com",
  "port": 443,
  "scheme": "https",
  "auth_endpoint": "/api/broadcasting/auth"
}
```

The `secret` is never included in the response.

---

## 2. Channel authentication

Private-channel auth for the mobile app goes through:

```
POST /api/broadcasting/auth
Authorization: Bearer <sanctum_token>
Content-Type: application/json

{
  "socket_id": "123.456",
  "channel_name": "private-user.42"
}
```

Response (Pusher protocol):

```json
{ "auth": "reverb_key:hmac_signature" }
```

This endpoint is separate from the web-only `/broadcasting/auth` route which
requires a session cookie. Mobile clients must always call `/api/broadcasting/auth`.

---

## 3. Pusher-JS client setup (Reverb-compatible)

```typescript
import Pusher from 'pusher-js/react-native';

const API_BASE = 'https://api.cleanux.com'; // your env var

async function buildPusherClient(bearerToken: string): Promise<Pusher> {
  const res = await fetch(`${API_BASE}/api/realtime/socket-config`, {
    headers: { Authorization: `Bearer ${bearerToken}`, Accept: 'application/json' },
  });
  if (!res.ok) throw new Error(`socket-config failed: ${res.status}`);

  const cfg = await res.json();

  return new Pusher(cfg.key, {
    wsHost: cfg.host,
    wsPort: cfg.port,
    wssPort: cfg.port,
    forceTLS: cfg.scheme === 'https',
    cluster: '',                        // not used with Reverb
    enabledTransports: ['ws', 'wss'],
    authorizer: (channel) => ({
      authorize: async (socketId: string, callback: Function) => {
        try {
          const r = await fetch(`${API_BASE}${cfg.auth_endpoint}`, {
            method: 'POST',
            headers: {
              Authorization: `Bearer ${bearerToken}`,
              'Content-Type': 'application/json',
              Accept: 'application/json',
            },
            body: JSON.stringify({
              socket_id: socketId,
              channel_name: channel.name,
            }),
          });
          if (!r.ok) throw new Error(`Auth failed: ${r.status}`);
          callback(null, await r.json());
        } catch (e) {
          callback(e as Error, { auth: '' });
        }
      },
    }),
  });
}
```

---

## 4. Available channels (from routes/channels.php)

| Channel pattern | Auth rule | Use case |
|---|---|---|
| `private-user.{userId}` | `$user->id === $userId` | Personal live notifications (UserLiveNotification) |
| `private-mission.{missionId}` | client of the booking, assigned provider, or admin | Mission GPS tracking, status updates, ETA (MissionPositionUpdated, MissionStatusUpdated, MissionLiveEta) |
| `private-channel.{channelId}` | member of the chat channel | Chat v2 messages (ChatMessageSentEvent) |
| `presence-org.{orgId}` | member of the organisation | Org-level presence |
| `presence-team.{teamId}` | member of the field team | Field-team presence |
| `providers.presence` | admin / dispatcher only | **Mobile clients must NOT subscribe** |

---

## 5. Example: subscribe to personal notifications

```typescript
const pusher = await buildPusherClient(myToken);

const ch = pusher.subscribe(`private-user.${myUserId}`);

ch.bind('UserLiveNotification', (event: {type: string; payload: unknown}) => {
  // handle push-like notification in foreground
  console.log('notification', event.type, event.payload);
});
```

## 6. Example: subscribe to active mission tracking

```typescript
const ch = pusher.subscribe(`private-mission.${missionId}`);

ch.bind('MissionPositionUpdated', (event: {lat: number; lng: number}) => {
  mapRef.current?.setMarker(event.lat, event.lng);
});

ch.bind('MissionLiveEta', (event: {eta_minutes: number}) => {
  setEta(event.eta_minutes);
});

ch.bind('MissionStatusUpdated', (event: {status: string}) => {
  setMissionStatus(event.status);
});
```

---

## 7. Reconnection / UX

Pusher-JS reconnects automatically. Show a "Reconnecting..." badge when:

```typescript
pusher.connection.bind('state_change', ({ current }: { current: string }) => {
  if (current === 'connecting') {
    // show badge after 3s to avoid flicker on quick reconnects
    setTimeout(() => {
      if (pusher.connection.state === 'connecting') setShowReconnecting(true);
    }, 3000);
  } else {
    setShowReconnecting(false);
  }
});
```

---

## 8. Server-side summary

| Route | Middleware | Purpose |
|---|---|---|
| `GET /api/realtime/socket-config` | `auth:sanctum, token.grace` | Discovery: returns driver/key/host/port/scheme |
| `POST /api/broadcasting/auth` | `auth:sanctum, token.grace` | Channel auth for mobile (Bearer, no cookies) |
| `POST /broadcasting/auth` | `web` (session) | Channel auth for Livewire/Echo-web — do not call from RN |
