import React, { Suspense, lazy } from 'react';

const EditorContainer = lazy(() => import('../containers/EditorContainer'));

const App = () => (
	<Suspense fallback="Loading">
		<EditorContainer />
	</Suspense>
);

export default App;