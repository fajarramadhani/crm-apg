import React from 'react'

interface LegacyWorkflowBadgeProps {
  isLegacy?: boolean;
}

export const LegacyWorkflowBadge: React.FC<LegacyWorkflowBadgeProps> = ({ isLegacy }) => {
  if (!isLegacy) return null

  return (
    <span className="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-800 border border-amber-200">
      <span className="h-2 w-2 rounded-full bg-amber-500 animate-pulse" />
      Legacy Workflow
    </span>
  )
}
