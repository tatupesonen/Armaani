import { useCallback, useEffect, useRef, useState } from 'react';
import { Progress } from '@/components/ui/progress';
import echo from '@/echo';

type LogViewerProps = {
    channel: string;
    event: string;
    maxHeight?: string;
    initialLines?: string[];
    trackProgress?: boolean;
    loadInitialLines?: () => Promise<string[]>;
    label?: string;
};

export default function LogViewer({
    channel,
    event,
    maxHeight = 'max-h-64',
    initialLines = [],
    trackProgress = false,
    loadInitialLines,
    label,
}: LogViewerProps) {
    const [lines, setLines] = useState<string[]>(initialLines);
    const [progress, setProgress] = useState(0);
    const containerRef = useRef<HTMLDivElement>(null);
    const isNearBottomRef = useRef(true);
    const maxLines = 5000;

    const scrollToBottom = useCallback(() => {
        if (containerRef.current) {
            containerRef.current.scrollTop = containerRef.current.scrollHeight;
        }
    }, []);

    const handleScroll = useCallback(() => {
        if (!containerRef.current) return;
        const { scrollTop, scrollHeight, clientHeight } = containerRef.current;
        isNearBottomRef.current = scrollHeight - scrollTop - clientHeight < 40;
    }, []);

    useEffect(() => {
        if (loadInitialLines) {
            loadInitialLines()
                .then((loaded) => {
                    setLines(loaded);
                    requestAnimationFrame(scrollToBottom);
                })
                .catch(() => {
                    // Silently handle fetch failures — the log viewer will show "Waiting for output..."
                });
        }
    }, [loadInitialLines, scrollToBottom]);

    useEffect(() => {
        const echoChannel = echo.private(channel);

        echoChannel.listen(
            event,
            (data: { line: string; progressPct?: number }) => {
                if (trackProgress && data.progressPct !== undefined) {
                    setProgress(data.progressPct);
                }

                setLines((prev) => {
                    const next = [...prev, data.line];
                    return next.length > maxLines
                        ? next.slice(-maxLines)
                        : next;
                });

                if (isNearBottomRef.current) {
                    requestAnimationFrame(scrollToBottom);
                }
            },
        );

        return () => {
            echo.leave(channel);
        };
    }, [channel, event, trackProgress, scrollToBottom]);

    return (
        <div>
            {label && (
                <p className="mb-2 text-xs font-medium text-muted-foreground">
                    {label}
                </p>
            )}

            {trackProgress && progress > 0 && progress < 100 && (
                <Progress value={progress} className="mb-2 h-1.5" />
            )}

            <div
                ref={containerRef}
                onScroll={handleScroll}
                className={`rounded bg-zinc-900 p-3 font-mono text-xs text-zinc-100 ${maxHeight} overflow-y-auto`}
            >
                {lines.length === 0 ? (
                    <div className="text-zinc-500">Waiting for output...</div>
                ) : (
                    lines.map((line, index) => (
                        <div
                            key={index}
                            className="break-all whitespace-pre-wrap"
                        >
                            {line}
                        </div>
                    ))
                )}
            </div>
        </div>
    );
}
