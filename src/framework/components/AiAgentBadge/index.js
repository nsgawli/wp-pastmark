import React from 'react';

import { Badge } from '@framework/components';

// Scoped exactly to `actor_type === 'ai_agent'` — module 13's roadmap names
// this specific "AI-agent badge" capability, not a general actor-type badge
// system, so this component has no other actor-type branches (human/system/
// scheduled render as plain text wherever they're shown instead).
const AiAgentBadge = () => <Badge type="info">AI Agent</Badge>;

export default AiAgentBadge;
