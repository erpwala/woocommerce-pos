import * as React from 'react';
import classNames from 'classnames';

type TabProps = {
	name: string;
	title: string;
} & Record<string, any>;

interface TabsProps {
	tabs: TabProps[];
	children: (tab: TabProps) => React.ReactElement;
	orientation?: 'horizontal' | 'vertical';
}

const Tabs = ({ children, tabs, orientation }: TabsProps) => {
	const [selected, setSelected] = React.useState<number>(0);

	return (
		<>
			<div className="wcpos-flex wcpos-space-x-4 wcpos-justify-center">
				{tabs.map((tab, index) => (
					<button
						key={tab.name}
						onClick={() => setSelected(index)}
						className={classNames(
							'wcpos-text-sm wcpos-px-4 wcpos-py-2 wcpos-border-b-4',
							selected === index ? 'wcpos-border-wp-admin-theme-color' : 'wcpos-border-transparent'
						)}
					>
						{tab.title}
					</button>
				))}
			</div>
			<div className="wcpos-p-4">{children(tabs[selected])}</div>
		</>
	);
};

export default Tabs;
