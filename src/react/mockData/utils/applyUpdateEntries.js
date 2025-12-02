export const currentEntries = {
  id_1: {
    id: 1,
    type: 'new',
    content: 'test',
  },
  id_2: {
    id: 2,
    type: 'new',
    content: 'test',
  },
  id_3: {
    id: 3,
    type: 'new',
    content: 'test',
  },
};

export const newEntries = [
  {
    id: 4,
    type: 'new',
    content: 'test',
  },
  {
    id: 3,
    type: 'update',
    content: 'updated',
  },
  {
    id: 2,
    type: 'delete',
    content: '',
  },
];

export const expectedEntries = {
  id_4: {
    id: 4,
    type: 'new',
    content: 'test',
  },
  id_1: {
    id: 1,
    type: 'new',
    content: 'test',
  },
  id_3: {
    id: 3,
    type: 'update',
    content: 'updated',
  },
};

export const expectedEntriesPolling = {
  id_1: {
    id: 1,
    type: 'new',
    content: 'test',
  },
  id_3: {
    id: 3,
    type: 'update',
    content: 'updated',
  },
};
