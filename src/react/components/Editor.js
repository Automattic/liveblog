import Loadable from 'react-loadable';

export default Loadable({
  loader: () =>
    import('../containers/EditorContainer'),
  loading() {
    return 'Loading';
  },
});
