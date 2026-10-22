"use client";
import type { ReactNode } from "react";
import { DragDropProvider, type DragDropEventHandlers } from "@dnd-kit/react";
import { isSortable } from "@dnd-kit/react/sortable";
import { GripVertical } from "lucide-react";
import { useSortable } from "@dnd-kit/react/sortable";

export function SortableFieldList({
    children,
    onMove,
    disabled = false,
}: {
    children: ReactNode;
    onMove: (from: number, to: number) => void;
    disabled?: boolean;
}) {
    const onDragEnd: DragDropEventHandlers["onDragEnd"] = (event) => {
        const source = event.operation.source;
        if (
            disabled ||
            event.canceled ||
            !source ||
            !isSortable(source) ||
            source.initialIndex === source.index
        )
            return;
        onMove(source.initialIndex, source.index);
    };
    return (
        <DragDropProvider onDragEnd={onDragEnd}>{children}</DragDropProvider>
    );
}
export function SortableFieldRow({
    id,
    index,
    children,
    onRemove,
    disabled = false,
}: {
    id: string;
    index: number;
    children: ReactNode;
    onRemove: () => void;
    disabled?: boolean;
}) {
    const { ref, handleRef, isDragging } = useSortable({ id, index, disabled });
    return (
        <div
            ref={ref}
            className={`relative rounded-2xl border border-[#e6ece7] bg-[#fffdfa] p-4 transition-shadow ${isDragging ? "z-10 shadow-xl ring-2 ring-[#9bd5c8]" : ""}`}
        >
            <div className="absolute top-3 right-3 flex items-center gap-1">
                <button
                    ref={handleRef}
                    type="button"
                    disabled={disabled}
                    aria-label="ドラッグして順序を変更"
                    className="touch-none rounded-lg p-2 text-[#80908d] hover:bg-[#edf5f0] focus-visible:outline-2 focus-visible:outline-[#168c88]"
                >
                    <GripVertical size={18} />
                </button>
                <button
                    type="button"
                    disabled={disabled}
                    onClick={onRemove}
                    aria-label="この行を削除"
                    className="rounded-lg px-2.5 py-2 text-xs font-semibold text-[#c87865] hover:bg-[#fff0ec]"
                >
                    削除
                </button>
            </div>
            {children}
        </div>
    );
}
