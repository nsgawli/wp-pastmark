import React, { useEffect } from 'react';
import { IoMdClose } from 'react-icons/io';
import './index.css';

const ModalPopup = ({
	isOpen,
	onClose,
	children,
	size = 'medium',
	closeIcon = true,
}) => {
	useEffect(() => {
		const handleEsc = (event) => {
			if (event.keyCode === 27) onClose(); // 27 is the ESC key
		};

		if (isOpen) {
			document.addEventListener('keydown', handleEsc);
		}

		return () => {
			document.removeEventListener('keydown', handleEsc);
		};
	}, [isOpen, onClose]); // Re-run the effect if isOpen or onClose changes

	if (!isOpen) return null;

	// Function to stop propagation for modal content clicks
	const stopPropagation = (e) => {
		e.stopPropagation();
	};

	return (
		<div className="modal-backdrop" onClick={onClose}>
			<div
				className="modal-content"
				onClick={stopPropagation}
				style={{
					width:
						size === 'small'
							? '400px'
							: size === 'medium'
								? '600px'
								: '800px',
				}}
			>
				{children}
				{closeIcon && (
					<IoMdClose
						className="psm-modal-close-icon"
						onClick={onClose}
					/>
				)}
			</div>
		</div>
	);
};

export default ModalPopup;
