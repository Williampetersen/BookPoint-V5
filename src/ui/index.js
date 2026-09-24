/**
 * BookPoint UI library (see DESIGN-SYSTEM.md).
 *
 * Tokens and base styles are imported by ThemeRoot (every app renders one); each component
 * imports its own CSS, so bundles only contain the components they use.
 */

export {
	ThemeRoot,
	Portal,
	useTheme,
	useResolvedTheme,
} from './theme/ThemeRoot';
export {
	brandTokens,
	DEFAULT_BRAND,
	isHex,
	contrast,
	parseHex,
} from './theme/brand';
export { Icon, ICONS } from './icons';
export { Button, IconButton, Spinner } from './components/Button';
export {
	Field,
	Input,
	SearchInput,
	Textarea,
	Select,
	Checkbox,
	Toggle,
} from './components/Form';
export { RadioCards, ChoiceCard } from './components/Choice';
export {
	Calendar,
	DateInput,
	monthOf,
	shiftMonth,
	monthMatrix,
	availabilityLabel,
} from './components/Calendar';
export { TimeSlots } from './components/TimeSlots';
export {
	Modal,
	Drawer,
	ConfirmProvider,
	useConfirm,
} from './components/Overlay';
export { Tabs } from './components/Tabs';
export { Stepper } from './components/Stepper';
export {
	Card,
	Badge,
	StatusBadge,
	STATUS_TONES,
	statusLabel,
	Avatar,
	initials,
} from './components/Display';
export { Table, Pagination } from './components/Table';
export { Popover, Dropdown, computePosition } from './components/Popover';
export { Tooltip } from './components/Tooltip';
export { ToastProvider, useToast } from './components/Toast';
export {
	Skeleton,
	EmptyState,
	ErrorState,
	Notice,
} from './components/Feedback';
export * from './hooks';
