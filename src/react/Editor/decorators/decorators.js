import Link, { findLinkEntities } from './Link';
import Text, { findTextEntities } from './Text';

export default [
  {
    strategy: findLinkEntities,
    component: Link,
  },
  {
    strategy: findTextEntities,
    component: Text,
  },
];
