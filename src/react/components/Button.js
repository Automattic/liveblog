import React from 'react';
import PropTypes from 'prop-types';

const addModifiers = ( modifiers ) =>
	modifiers
		.split( ' ' )
		.map( ( x ) => `liveblog-btn--${ x }` )
		.join( ' ' );

const Button = ( { children, click, type, modifiers, onMouseDown } ) => (
	<span>
		<button
			className={ `liveblog-btn ${
				type && `liveblog-btn--${ type }`
			} ${ addModifiers( modifiers ) }` }
			onMouseDown={ onMouseDown ? ( e ) => e.preventDefault() : null }
			onClick={ onMouseDown || click }
		>
			{ children }
		</button>
	</span>
);

Button.propTypes = {
	children: PropTypes.string,
	click: PropTypes.func,
	onMouseDown: PropTypes.func,
	type: PropTypes.string,
	modifiers: PropTypes.string,
};

export default Button;
