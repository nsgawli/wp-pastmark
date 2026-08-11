import React from 'react';

import { Badge } from '@framework/components';

const EventBadge = ({ event = '' }) => {

	return <Badge type="info">{event}</Badge>;
};

export default EventBadge;
