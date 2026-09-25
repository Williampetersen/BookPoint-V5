/**
 * Step illustrations: simple geometric scenes in the brand colours (recolour with the brand).
 * A custom image from Booking form → Steps replaces them.
 */

const Frame = ( { children } ) => (
	<svg
		className="pbk-art"
		viewBox="0 0 160 120"
		width="160"
		height="120"
		fill="none"
		aria-hidden="true"
		focusable="false"
	>
		<rect
			x="8"
			y="10"
			width="144"
			height="100"
			rx="22"
			className="pbk-art__bg"
		/>
		{ children }
	</svg>
);

const SCENES = {
	location: (
		<Frame>
			<path
				d="M20 86c22-10 40 6 62-4s38-20 58-8"
				className="pbk-art__line"
			/>
			<path
				d="M80 30c-11 0-19 8-19 19 0 14 19 31 19 31s19-17 19-31c0-11-8-19-19-19z"
				className="pbk-art__solid"
			/>
			<circle cx="80" cy="49" r="7" className="pbk-art__hole" />
		</Frame>
	),
	category: (
		<Frame>
			<rect
				x="40"
				y="30"
				width="34"
				height="26"
				rx="8"
				className="pbk-art__solid"
			/>
			<rect
				x="86"
				y="30"
				width="34"
				height="26"
				rx="8"
				className="pbk-art__soft"
			/>
			<rect
				x="40"
				y="64"
				width="34"
				height="26"
				rx="8"
				className="pbk-art__soft"
			/>
			<rect
				x="86"
				y="64"
				width="34"
				height="26"
				rx="8"
				className="pbk-art__soft"
			/>
		</Frame>
	),
	service: (
		<Frame>
			<rect
				x="36"
				y="32"
				width="88"
				height="56"
				rx="14"
				className="pbk-art__card"
			/>
			<circle cx="58" cy="60" r="11" className="pbk-art__solid" />
			<rect
				x="76"
				y="52"
				width="36"
				height="6"
				rx="3"
				className="pbk-art__soft"
			/>
			<rect
				x="76"
				y="64"
				width="24"
				height="6"
				rx="3"
				className="pbk-art__soft"
			/>
			<path
				d="M120 26l3 7 7 3-7 3-3 7-3-7-7-3 7-3z"
				className="pbk-art__solid"
			/>
		</Frame>
	),
	extras: (
		<Frame>
			<rect
				x="34"
				y="40"
				width="40"
				height="40"
				rx="12"
				className="pbk-art__card"
			/>
			<rect
				x="86"
				y="40"
				width="40"
				height="40"
				rx="12"
				className="pbk-art__solid"
			/>
			<path d="M106 51v18M97 60h18" className="pbk-art__stroke-light" />
			<path d="M46 60h16" className="pbk-art__line" />
		</Frame>
	),
	agents: (
		<Frame>
			<circle cx="60" cy="52" r="12" className="pbk-art__soft" />
			<path
				d="M38 88c3-12 12-18 22-18s19 6 22 18"
				className="pbk-art__soft"
			/>
			<circle cx="100" cy="48" r="14" className="pbk-art__solid" />
			<path
				d="M74 90c3-14 14-21 26-21s23 7 26 21"
				className="pbk-art__solid"
			/>
		</Frame>
	),
	datetime: (
		<Frame>
			<rect
				x="36"
				y="30"
				width="72"
				height="62"
				rx="12"
				className="pbk-art__card"
			/>
			<path d="M36 46h72" className="pbk-art__line" />
			<rect
				x="46"
				y="56"
				width="10"
				height="10"
				rx="3"
				className="pbk-art__soft"
			/>
			<rect
				x="62"
				y="56"
				width="10"
				height="10"
				rx="3"
				className="pbk-art__solid"
			/>
			<rect
				x="78"
				y="56"
				width="10"
				height="10"
				rx="3"
				className="pbk-art__soft"
			/>
			<rect
				x="46"
				y="72"
				width="10"
				height="10"
				rx="3"
				className="pbk-art__soft"
			/>
			<circle cx="112" cy="78" r="18" className="pbk-art__solid" />
			<path d="M112 69v9l6 4" className="pbk-art__stroke-light" />
		</Frame>
	),
	customer: (
		<Frame>
			<rect
				x="34"
				y="34"
				width="92"
				height="54"
				rx="12"
				className="pbk-art__card"
			/>
			<circle cx="58" cy="58" r="10" className="pbk-art__solid" />
			<path
				d="M46 78c2-6 7-9 12-9s10 3 12 9"
				className="pbk-art__solid"
			/>
			<rect
				x="80"
				y="50"
				width="34"
				height="6"
				rx="3"
				className="pbk-art__soft"
			/>
			<rect
				x="80"
				y="62"
				width="24"
				height="6"
				rx="3"
				className="pbk-art__soft"
			/>
		</Frame>
	),
	payment: (
		<Frame>
			<rect
				x="32"
				y="36"
				width="96"
				height="58"
				rx="12"
				className="pbk-art__solid"
			/>
			<path d="M32 54h96" className="pbk-art__stroke-light" />
			<rect
				x="42"
				y="70"
				width="28"
				height="8"
				rx="4"
				className="pbk-art__hole"
			/>
		</Frame>
	),
	review: (
		<Frame>
			<rect
				x="44"
				y="26"
				width="72"
				height="72"
				rx="12"
				className="pbk-art__card"
			/>
			<path
				d="m56 46 4 4 8-8M56 64l4 4 8-8M56 82l4 4 8-8"
				className="pbk-art__line"
			/>
			<rect
				x="76"
				y="44"
				width="28"
				height="6"
				rx="3"
				className="pbk-art__soft"
			/>
			<rect
				x="76"
				y="62"
				width="28"
				height="6"
				rx="3"
				className="pbk-art__soft"
			/>
			<rect
				x="76"
				y="80"
				width="20"
				height="6"
				rx="3"
				className="pbk-art__soft"
			/>
		</Frame>
	),
};

export default function StepArt( { stepKey, design } ) {
	const step =
		( ( design && design.steps ) || [] ).find(
			( item ) => item.key === stepKey
		) || {};
	if ( step.imageUrl ) {
		return (
			<img
				className="pbk-art pbk-art--image"
				src={ step.imageUrl }
				alt=""
				loading="lazy"
				decoding="async"
			/>
		);
	}
	return SCENES[ step.image ] || SCENES[ stepKey ] || SCENES.service;
}
