import Echo, { type ConnectionStatus } from "laravel-echo";
import Pusher from "pusher-js";
import { useEffect, useEffectEvent, useState } from "react";

export type RealtimeStatus =
    | "disabled"
    | "connecting"
    | "reconnecting"
    | "connected"
    | "polling"
    | "offline";

type ChannelType = "public" | "private";

type UseRealtimeOptions = {
    enabled: boolean;
    channel: string;
    channelType: ChannelType;
    event: string;
    onEvent: (payload: unknown) => void;
    onRefresh: () => Promise<void> | void;
    pollInterval?: number;
};

function createEcho(): Echo<"reverb"> | null {
    const key = import.meta.env.VITE_REVERB_APP_KEY;
    const host = import.meta.env.VITE_REVERB_HOST;

    if (!key || !host) {
        return null;
    }

    const scheme = import.meta.env.VITE_REVERB_SCHEME || "http";
    const configuredPort = Number(import.meta.env.VITE_REVERB_PORT);
    const port =
        Number.isFinite(configuredPort) && configuredPort > 0
            ? configuredPort
            : scheme === "https"
              ? 443
              : 80;

    return new Echo({
        broadcaster: "reverb",
        key,
        wsHost: host,
        wsPort: port,
        wssPort: port,
        forceTLS: scheme === "https",
        enabledTransports: ["ws", "wss"],
        Pusher,
    });
}

function connectionStatus(status: ConnectionStatus): RealtimeStatus {
    if (status === "connected") {
        return "connected";
    }

    if (status === "reconnecting" || status === "disconnected") {
        return "reconnecting";
    }

    if (status === "failed") {
        return "offline";
    }

    return "connecting";
}

export function useRealtime({
    enabled,
    channel,
    channelType,
    event,
    onEvent,
    onRefresh,
    pollInterval = 15_000,
}: UseRealtimeOptions): RealtimeStatus {
    const [status, setStatus] = useState<RealtimeStatus>(enabled ? "connecting" : "disabled");
    const onEventEvent = useEffectEvent(onEvent);
    const onRefreshEvent = useEffectEvent(onRefresh);

    useEffect(() => {
        if (!enabled) {
            setStatus("disabled");

            return;
        }

        let disposed = false;
        let pollTimer: number | null = null;
        let pollInFlight = false;

        const poll = async (): Promise<void> => {
            if (disposed || pollInFlight) {
                return;
            }

            pollInFlight = true;

            try {
                await onRefreshEvent();

                if (!disposed) {
                    setStatus((current) => (current === "offline" ? "polling" : current));
                }
            } catch {
                if (!disposed) {
                    setStatus("offline");
                }
            } finally {
                pollInFlight = false;
            }
        };

        const startPolling = (): void => {
            if (pollTimer !== null) {
                return;
            }

            void poll();
            pollTimer = window.setInterval(() => void poll(), pollInterval);
        };

        const stopPolling = (): void => {
            if (pollTimer === null) {
                return;
            }

            window.clearInterval(pollTimer);
            pollTimer = null;
        };

        if (channel === "") {
            setStatus("polling");
            startPolling();

            return () => {
                disposed = true;
                stopPolling();
            };
        }

        const handleConnectionChange = (connection: ConnectionStatus): void => {
            if (disposed) {
                return;
            }

            const nextStatus = connectionStatus(connection);
            setStatus(nextStatus);

            if (nextStatus === "connected") {
                stopPolling();
                void poll();
            } else {
                startPolling();
            }
        };

        let echo: Echo<"reverb"> | null = null;

        try {
            echo = createEcho();
        } catch {
            setStatus("offline");
            startPolling();
        }

        if (echo === null) {
            if (pollTimer === null) {
                setStatus("polling");
                startPolling();
            }

            return () => {
                disposed = true;
                stopPolling();
            };
        }

        const unsubscribeConnection = echo.connector.onConnectionChange(handleConnectionChange);
        const realtimeChannel =
            channelType === "private" ? echo.private(channel) : echo.channel(channel);

        realtimeChannel.listen(event, (payload: unknown) => {
            if (disposed) {
                return;
            }

            setStatus("connected");
            onEventEvent(payload);
        });
        realtimeChannel.error(() => {
            if (!disposed) {
                setStatus("offline");
                startPolling();
            }
        });

        handleConnectionChange(echo.connectionStatus());

        return () => {
            disposed = true;
            stopPolling();
            unsubscribeConnection();
            echo?.leave(channel);
            echo?.disconnect();
        };
    }, [channel, channelType, enabled, event, pollInterval]);

    return status;
}
