import React from 'react';
import { __ } from '@wordpress/i18n';
import { Card, Flex, Button } from '@framework/components';
import { BsPuzzle } from 'react-icons/bs';
import './index.css';

const SUPPORT_URL = 'https://wordpress.org/support/plugin/pastmark/';

const RequestIntegration = () => {
	return (
		<Card className="wppm-request-integration-card">
			<Flex align="center" gap={14} wrap>
				<div className="wppm-request-integration-icon">
					<BsPuzzle />
				</div>

				<div className="wppm-request-integration-copy">
					<div className="wppm-request-integration-title">
						{__("Don't see your plugin?", 'pastmark')}
					</div>
					<div className="wppm-request-integration-description">
						{__(
							"We're adding new integrations regularly. Let us know which plugin you'd like us to support next.",
							'pastmark'
						)}
					</div>
				</div>

				<Button
					type="primary"
					className="wppm-request-integration-button"
					icon={<BsPuzzle />}
					onClick={() => {
						window.open(SUPPORT_URL, '_blank');
					}}
				>
					{__('Request an Integration', 'pastmark')}
				</Button>
			</Flex>
		</Card>
	);
};

export default RequestIntegration;
