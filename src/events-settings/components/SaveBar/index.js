import React from 'react';
import { Flex, Button } from '@framework/components';

const SaveBar = ({ hasChanges = false, loading = false, onSave = null }) => {
	if (!hasChanges) {
		return null;
	}

	return (
		<Flex
			justify="flex-end"
			style={{
				marginTop: '20px',
			}}
		>
			<Button type="primary" loading={loading} onClick={onSave}>
				Save Changes
			</Button>
		</Flex>
	);
};

export default SaveBar;
