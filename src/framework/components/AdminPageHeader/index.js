import React from 'react';
import { __ } from '@wordpress/i18n';
import { Flex, Button, Title } from '@framework/components';
import { useScreenSize } from '@framework/hooks/useScreenSize';
import { BsBook, BsQuestionCircle } from 'react-icons/bs';
import './index.css';

const AdminPageHeader = ({ icon, title, docLink, helpLink }) => {
	const { screenSize } = useScreenSize();

	return (
		<Flex
			className="psmfr-admin-page-header"
			justify="space-between"
			align="center"
		>
			<Flex align="center" gap={10}>
				{icon}
				<Title level={1}>{title}</Title>
			</Flex>
			{screenSize != 'xs' && (docLink || helpLink) && (
				<Flex gap={5}>
					{docLink && (
						<Button
							size="small"
							icon={<BsBook />}
							onClick={() => {
								window.open(docLink, '_blank');
							}}
						>
							{__('Documentation', 'psmwraq')}
						</Button>
					)}
					{helpLink && (
						<Button
							size="small"
							icon={<BsQuestionCircle />}
							onClick={() => {
								window.open(helpLink, '_blank');
							}}
						>
							{__('Support', 'psmwraq')}
						</Button>
					)}
				</Flex>
			)}
		</Flex>
	);
};

export default AdminPageHeader;
