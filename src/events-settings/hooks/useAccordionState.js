import { useState } from 'react';

const useAccordionState = () => {
	const [expanded, setExpanded] = useState({});

	const toggle = (key) => {
		setExpanded((prev) => ({
			...prev,
			[key]: !prev[key],
		}));
	};

	return {
		expanded,
		toggle,
	};
};

export default useAccordionState;
