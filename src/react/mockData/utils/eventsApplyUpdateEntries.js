export const currentEvents = {
  id_1: {
    id: 1,
    type: 'new',
    content: 'test',
    key_event: true,
  },
  id_2: {
    id: 2,
    type: 'new',
    content: 'test',
    key_event: true,
  },
  id_3: {
    id: 3,
    type: 'new',
    content: 'test',
    key_event: true,
  },
};

export const newEvents = [
  {
    id: 4,
    type: 'new',
    content: 'test',
    key_event: true,
  },
  {
    id: 3,
    type: 'update',
    content: 'updated',
    key_event: true,
  },
  {
    id: 2,
    type: 'update',
    content: '',
    key_event: false,
  },
];

export const expectedEvents = {
  id_4: {
    id: 4,
    type: 'new',
    content: 'test',
    key_event: true,
  },
  id_1: {
    id: 1,
    type: 'new',
    content: 'test',
    key_event: true,
  },
  id_3: {
    id: 3,
    type: 'update',
    content: 'updated',
    key_event: true,
  },
};
