import { daysBetween, formattedTime, timeAgo, getCurrentTimestamp } from '../utils';

describe('time utils', () => {
  const twentiethNovember2017 = 1511136000 * 1000;
  const twentyFifthNovember2017 = 1511568000 * 1000;

  it('daysBetween should return the correct days between two timestamps', () => {
    expect(
      daysBetween(twentyFifthNovember2017, twentiethNovember2017),
    ).toEqual(5);
  });

  const ThreeMinutesPastTwelve = 1511956980;

  it('formattedTime should return time in h:m format', () => {
    expect(
      formattedTime(ThreeMinutesPastTwelve),
    ).toEqual('12:03');
  });

  const twentyNinthNovember2016 = 1480424162;

  it('timeAgo to show the correct date if it is more than 30 days ago', () => {
    expect(
      timeAgo(twentyNinthNovember2016),
    ).toEqual('29/11/2016');
  });

  it('getCurrentTimestamp should return the current timestamp', () => {
    expect(getCurrentTimestamp()).toEqual(Math.floor(Date.now() / 1000));
  });

  const tenMintesAgo = Math.floor(Date.now() / 1000) - 600;

  it('timeAgo to show the correct time if it is less than 30 days ago', () => {
    expect(
      timeAgo(tenMintesAgo),
    ).toEqual('10m ago');
  });
});
