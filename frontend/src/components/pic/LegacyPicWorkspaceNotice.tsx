import React from 'react'
import { AlertCircle, ExternalLink } from 'lucide-react'

interface LegacyPicWorkspaceNoticeProps {
  ticketId: number | string
}

export const LegacyPicWorkspaceNotice: React.FC<LegacyPicWorkspaceNoticeProps> = ({ ticketId }) => {
  return (
    <div className="p-4 bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800 rounded-xl flex flex-col md:flex-row items-start md:items-center justify-between gap-3 text-xs text-amber-900 dark:text-amber-200">
      <div className="flex items-start gap-2.5">
        <AlertCircle className="w-5 h-5 text-amber-600 shrink-0 mt-0.5" />
        <div>
          <div className="font-bold flex items-center gap-2">
            <span>Tiket Legacy (Workflow Lama)</span>
            <span className="px-2 py-0.5 rounded-full bg-amber-200 dark:bg-amber-800 text-amber-900 dark:text-amber-100 font-semibold text-[10px]">
              Legacy Workflow
            </span>
          </div>
          <p className="mt-0.5 text-amber-800 dark:text-amber-300">
            Tiket ini dibuat dengan alur kerja legacy (Solution Planning / QA Defect / UAT Rework lama). Data QA, UAT, dan Release lama tetap tersimpan utuh.
          </p>
        </div>
      </div>

      <div className="flex items-center gap-2 shrink-0 self-end md:self-auto">
        <a
          href={`/pic/workspace?ticket_id=${ticketId}`}
          className="inline-flex items-center gap-1.5 px-3 py-1.5 font-semibold bg-amber-600 hover:bg-amber-700 text-white rounded-lg transition text-xs"
        >
          Buka Workspace Legacy <ExternalLink className="w-3.5 h-3.5" />
        </a>
      </div>
    </div>
  )
}
