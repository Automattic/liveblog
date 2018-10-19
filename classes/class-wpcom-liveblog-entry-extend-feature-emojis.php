<?php

/**
 * Class WPCOM_Liveblog_Entry_Extend_Feature_Emojis.
 *
 * The base class for autocomplete features.
 */
class WPCOM_Liveblog_Entry_Extend_Feature_Emojis extends WPCOM_Liveblog_Entry_Extend_Feature {

	/**
	 * The class prefix.
	 *
	 * @var string
	 */
	protected $class_prefix = 'emoji-';

	/**
	 * Path to use with plugins_url.
	 *
	 * @var string
	 */
	protected $path;

	/**
	 * The emojis.
	 *
	 * @var string
	 */
	protected $emojis = array(
		'+1'                              => '1F44D',
		'-1'                              => '1F44E',
		'100'                             => '1F4AF',
		'1234'                            => '1F522',
		'8ball'                           => '1F3B1',
		'a'                               => '1F170',
		'ab'                              => '1F18E',
		'abc'                             => '1F524',
		'abcd'                            => '1F521',
		'accept'                          => '1F251',
		'aerial_tramway'                  => '1F6A1',
		'airplane'                        => '2708',
		'alarm_clock'                     => '23F0',
		'alien'                           => '1F47D',
		'ambulance'                       => '1F691',
		'anchor'                          => '2693',
		'angel'                           => '1F47C',
		'anger'                           => '1F4A2',
		'angry'                           => '1F620',
		'anguished'                       => '1F627',
		'ant'                             => '1F41C',
		'apple'                           => '1F34E',
		'aquarius'                        => '2652',
		'aries'                           => '2648',
		'arrow_backward'                  => '25C0',
		'arrow_double_down'               => '23EC',
		'arrow_double_up'                 => '23EB',
		'arrow_down'                      => '2B07',
		'arrow_down_small'                => '1F53D',
		'arrow_forward'                   => '25B6',
		'arrow_heading_down'              => '2935',
		'arrow_heading_up'                => '2934',
		'arrow_left'                      => '2B05',
		'arrow_lower_left'                => '2199',
		'arrow_lower_right'               => '2198',
		'arrow_right'                     => '27A1',
		'arrow_right_hook'                => '21AA',
		'arrow_up'                        => '2B06',
		'arrow_up_down'                   => '2195',
		'arrow_up_small'                  => '1F53C',
		'arrow_upper_left'                => '2196',
		'arrow_upper_right'               => '2197',
		'arrows_clockwise'                => '1F503',
		'arrows_counterclockwise'         => '1F504',
		'art'                             => '1F3A8',
		'articulated_lorry'               => '1F69B',
		'astonished'                      => '1F632',
		'athletic_shoe'                   => '1F45F',
		'atm'                             => '1F3E7',
		'b'                               => '1F171',
		'baby'                            => '1F476',
		'baby_bottle'                     => '1F37C',
		'baby_chick'                      => '1F424',
		'baby_symbol'                     => '1F6BC',
		'back'                            => '1F519',
		'baggage_claim'                   => '1F6C4',
		'balloon'                         => '1F388',
		'ballot_box_with_check'           => '2611',
		'bamboo'                          => '1F38D',
		'banana'                          => '1F34C',
		'bangbang'                        => '203C',
		'bank'                            => '1F3E6',
		'bar_chart'                       => '1F4CA',
		'barber'                          => '1F488',
		'baseball'                        => '26BE',
		'basketball'                      => '1F3C0',
		'bath'                            => '1F6C0',
		'bathtub'                         => '1F6C1',
		'battery'                         => '1F50B',
		'bear'                            => '1F43B',
		'bee'                             => '1F41D',
		'beer'                            => '1F37A',
		'beers'                           => '1F37B',
		'beetle'                          => '1F41E',
		'beginner'                        => '1F530',
		'bell'                            => '1F514',
		'bento'                           => '1F371',
		'bicyclist'                       => '1F6B4',
		'bike'                            => '1F6B2',
		'bikini'                          => '1F459',
		'bird'                            => '1F426',
		'birthday'                        => '1F382',
		'black_circle'                    => '26AB',
		'black_joker'                     => '1F0CF',
		'black_large_square'              => '2B1B',
		'black_medium_small_square'       => '25FE',
		'black_medium_square'             => '25FC',
		'black_nib'                       => '2712',
		'black_small_square'              => '25AA',
		'black_square_button'             => '1F532',
		'blossom'                         => '1F33C',
		'blowfish'                        => '1F421',
		'blue_book'                       => '1F4D8',
		'blue_car'                        => '1F699',
		'blue_heart'                      => '1F499',
		'blush'                           => '1F60A',
		'boar'                            => '1F417',
		'boat'                            => '26F5',
		'bomb'                            => '1F4A3',
		'book'                            => '1F4D6',
		'bookmark'                        => '1F516',
		'bookmark_tabs'                   => '1F4D1',
		'books'                           => '1F4DA',
		'boom'                            => '1F4A5',
		'boot'                            => '1F462',
		'bouquet'                         => '1F490',
		'bow'                             => '1F647',
		'bowling'                         => '1F3B3',
		'boy'                             => '1F466',
		'bread'                           => '1F35E',
		'bride_with_veil'                 => '1F470',
		'bridge_at_night'                 => '1F309',
		'briefcase'                       => '1F4BC',
		'broken_heart'                    => '1F494',
		'bug'                             => '1F41B',
		'bulb'                            => '1F4A1',
		'bullettrain_front'               => '1F685',
		'bullettrain_side'                => '1F684',
		'bus'                             => '1F68C',
		'busstop'                         => '1F68F',
		'bust_in_silhouette'              => '1F464',
		'busts_in_silhouette'             => '1F465',
		'cactus'                          => '1F335',
		'cake'                            => '1F370',
		'calendar'                        => '1F4C6',
		'calling'                         => '1F4F2',
		'camel'                           => '1F42B',
		'camera'                          => '1F4F7',
		'cancer'                          => '264B',
		'candy'                           => '1F36C',
		'capital_abcd'                    => '1F520',
		'capricorn'                       => '2651',
		'car'                             => '1F697',
		'card_index'                      => '1F4C7',
		'carousel_horse'                  => '1F3A0',
		'cat'                             => '1F431',
		'cat2'                            => '1F408',
		'cd'                              => '1F4BF',
		'chart'                           => '1F4B9',
		'chart_with_downwards_trend'      => '1F4C9',
		'chart_with_upwards_trend'        => '1F4C8',
		'checkered_flag'                  => '1F3C1',
		'cherries'                        => '1F352',
		'cherry_blossom'                  => '1F338',
		'chestnut'                        => '1F330',
		'chicken'                         => '1F414',
		'children_crossing'               => '1F6B8',
		'chocolate_bar'                   => '1F36B',
		'christmas_tree'                  => '1F384',
		'church'                          => '26EA',
		'cinema'                          => '1F3A6',
		'circus_tent'                     => '1F3AA',
		'city_sunrise'                    => '1F307',
		'city_sunset'                     => '1F306',
		'cl'                              => '1F191',
		'clap'                            => '1F44F',
		'clapper'                         => '1F3AC',
		'clipboard'                       => '1F4CB',
		'clock1'                          => '1F550',
		'clock10'                         => '1F559',
		'clock1030'                       => '1F565',
		'clock11'                         => '1F55A',
		'clock1130'                       => '1F566',
		'clock12'                         => '1F55B',
		'clock1230'                       => '1F567',
		'clock130'                        => '1F55C',
		'clock2'                          => '1F551',
		'clock230'                        => '1F55D',
		'clock3'                          => '1F552',
		'clock330'                        => '1F55E',
		'clock4'                          => '1F553',
		'clock430'                        => '1F55F',
		'clock5'                          => '1F554',
		'clock530'                        => '1F560',
		'clock6'                          => '1F555',
		'clock630'                        => '1F561',
		'clock7'                          => '1F556',
		'clock730'                        => '1F562',
		'clock8'                          => '1F557',
		'clock830'                        => '1F563',
		'clock9'                          => '1F558',
		'clock930'                        => '1F564',
		'closed_book'                     => '1F4D5',
		'closed_lock_with_key'            => '1F510',
		'closed_umbrella'                 => '1F302',
		'cloud'                           => '2601',
		'clubs'                           => '2663',
		'cocktail'                        => '1F378',
		'coffee'                          => '2615',
		'cold_sweat'                      => '1F630',
		'collision'                       => '1F4A5',
		'computer'                        => '1F4BB',
		'confetti_ball'                   => '1F38A',
		'confounded'                      => '1F616',
		'confused'                        => '1F615',
		'congratulations'                 => '3297',
		'construction'                    => '1F6A7',
		'construction_worker'             => '1F477',
		'convenience_store'               => '1F3EA',
		'cookie'                          => '1F36A',
		'cool'                            => '1F192',
		'cop'                             => '1F46E',
		'copyright'                       => 'A9',
		'corn'                            => '1F33D',
		'couple'                          => '1F46B',
		'couple_with_heart'               => '1F491',
		'couplekiss'                      => '1F48F',
		'cow'                             => '1F42E',
		'cow2'                            => '1F404',
		'credit_card'                     => '1F4B3',
		'crescent_moon'                   => '1F319',
		'crocodile'                       => '1F40A',
		'crossed_flags'                   => '1F38C',
		'crown'                           => '1F451',
		'cry'                             => '1F622',
		'crying_cat_face'                 => '1F63F',
		'crystal_ball'                    => '1F52E',
		'cupid'                           => '1F498',
		'curly_loop'                      => '27B0',
		'currency_exchange'               => '1F4B1',
		'curry'                           => '1F35B',
		'custard'                         => '1F36E',
		'customs'                         => '1F6C3',
		'cyclone'                         => '1F300',
		'dancer'                          => '1F483',
		'dancers'                         => '1F46F',
		'dango'                           => '1F361',
		'dart'                            => '1F3AF',
		'dash'                            => '1F4A8',
		'date'                            => '1F4C5',
		'deciduous_tree'                  => '1F333',
		'department_store'                => '1F3EC',
		'diamond_shape_with_a_dot_inside' => '1F4A0',
		'diamonds'                        => '2666',
		'disappointed'                    => '1F61E',
		'disappointed_relieved'           => '1F625',
		'dizzy'                           => '1F4AB',
		'dizzy_face'                      => '1F635',
		'do_not_litter'                   => '1F6AF',
		'dog'                             => '1F436',
		'dog2'                            => '1F415',
		'dollar'                          => '1F4B5',
		'dolls'                           => '1F38E',
		'dolphin'                         => '1F42C',
		'door'                            => '1F6AA',
		'doughnut'                        => '1F369',
		'dragon'                          => '1F409',
		'dragon_face'                     => '1F432',
		'dress'                           => '1F457',
		'dromedary_camel'                 => '1F42A',
		'droplet'                         => '1F4A7',
		'dvd'                             => '1F4C0',
		'e-mail'                          => '1F4E7',
		'ear'                             => '1F442',
		'ear_of_rice'                     => '1F33E',
		'earth_africa'                    => '1F30D',
		'earth_americas'                  => '1F30E',
		'earth_asia'                      => '1F30F',
		'egg'                             => '1F373',
		'eggplant'                        => '1F346',
		'eight_pointed_black_star'        => '2734',
		'eight_spoked_asterisk'           => '2733',
		'electric_plug'                   => '1F50C',
		'elephant'                        => '1F418',
		'email'                           => '2709',
		'end'                             => '1F51A',
		'envelope'                        => '2709',
		'envelope_with_arrow'             => '1F4E9',
		'euro'                            => '1F4B6',
		'european_castle'                 => '1F3F0',
		'european_post_office'            => '1F3E4',
		'evergreen_tree'                  => '1F332',
		'exclamation'                     => '2757',
		'expressionless'                  => '1F611',
		'eyeglasses'                      => '1F453',
		'eyes'                            => '1F440',
		'facepunch'                       => '1F44A',
		'factory'                         => '1F3ED',
		'fallen_leaf'                     => '1F342',
		'family'                          => '1F46A',
		'fast_forward'                    => '23E9',
		'fax'                             => '1F4E0',
		'fearful'                         => '1F628',
		'feet'                            => '1F43E',
		'ferris_wheel'                    => '1F3A1',
		'file_folder'                     => '1F4C1',
		'fire'                            => '1F525',
		'fire_engine'                     => '1F692',
		'fireworks'                       => '1F386',
		'first_quarter_moon'              => '1F313',
		'first_quarter_moon_with_face'    => '1F31B',
		'fish'                            => '1F41F',
		'fish_cake'                       => '1F365',
		'fishing_pole_and_fish'           => '1F3A3',
		'fist'                            => '270A',
		'flags'                           => '1F38F',
		'flashlight'                      => '1F526',
		'flipper'                         => '1F42C',
		'floppy_disk'                     => '1F4BE',
		'flower_playing_cards'            => '1F3B4',
		'flushed'                         => '1F633',
		'foggy'                           => '1F301',
		'football'                        => '1F3C8',
		'footprints'                      => '1F463',
		'fork_and_knife'                  => '1F374',
		'fountain'                        => '26F2',
		'four_leaf_clover'                => '1F340',
		'free'                            => '1F193',
		'fried_shrimp'                    => '1F364',
		'fries'                           => '1F35F',
		'frog'                            => '1F438',
		'frowning'                        => '1F626',
		'fuelpump'                        => '26FD',
		'full_moon'                       => '1F315',
		'full_moon_with_face'             => '1F31D',
		'game_die'                        => '1F3B2',
		'gem'                             => '1F48E',
		'gemini'                          => '264A',
		'ghost'                           => '1F47B',
		'gift'                            => '1F381',
		'gift_heart'                      => '1F49D',
		'girl'                            => '1F467',
		'globe_with_meridians'            => '1F310',
		'goat'                            => '1F410',
		'golf'                            => '26F3',
		'grapes'                          => '1F347',
		'green_apple'                     => '1F34F',
		'green_book'                      => '1F4D7',
		'green_heart'                     => '1F49A',
		'grey_exclamation'                => '2755',
		'grey_question'                   => '2754',
		'grimacing'                       => '1F62C',
		'grin'                            => '1F601',
		'grinning'                        => '1F600',
		'guardsman'                       => '1F482',
		'guitar'                          => '1F3B8',
		'gun'                             => '1F52B',
		'haircut'                         => '1F487',
		'hamburger'                       => '1F354',
		'hammer'                          => '1F528',
		'hamster'                         => '1F439',
		'hand'                            => '270B',
		'handbag'                         => '1F45C',
		'hankey'                          => '1F4A9',
		'hatched_chick'                   => '1F425',
		'hatching_chick'                  => '1F423',
		'headphones'                      => '1F3A7',
		'hear_no_evil'                    => '1F649',
		'heart'                           => '2764',
		'heart_decoration'                => '1F49F',
		'heart_eyes'                      => '1F60D',
		'heart_eyes_cat'                  => '1F63B',
		'heartbeat'                       => '1F493',
		'heartpulse'                      => '1F497',
		'hearts'                          => '2665',
		'heavy_check_mark'                => '2714',
		'heavy_division_sign'             => '2797',
		'heavy_dollar_sign'               => '1F4B2',
		'heavy_exclamation_mark'          => '2757',
		'heavy_minus_sign'                => '2796',
		'heavy_multiplication_x'          => '2716',
		'heavy_plus_sign'                 => '2795',
		'helicopter'                      => '1F681',
		'herb'                            => '1F33F',
		'hibiscus'                        => '1F33A',
		'high_brightness'                 => '1F506',
		'high_heel'                       => '1F460',
		'hocho'                           => '1F52A',
		'honey_pot'                       => '1F36F',
		'honeybee'                        => '1F41D',
		'horse'                           => '1F434',
		'horse_racing'                    => '1F3C7',
		'hospital'                        => '1F3E5',
		'hotel'                           => '1F3E8',
		'hotsprings'                      => '2668',
		'hourglass'                       => '231B',
		'hourglass_flowing_sand'          => '23F3',
		'house'                           => '1F3E0',
		'house_with_garden'               => '1F3E1',
		'hushed'                          => '1F62F',
		'ice_cream'                       => '1F368',
		'icecream'                        => '1F366',
		'id'                              => '1F194',
		'ideograph_advantage'             => '1F250',
		'imp'                             => '1F47F',
		'inbox_tray'                      => '1F4E5',
		'incoming_envelope'               => '1F4E8',
		'information_desk_person'         => '1F481',
		'information_source'              => '2139',
		'innocent'                        => '1F607',
		'interrobang'                     => '2049',
		'iphone'                          => '1F4F1',
		'izakaya_lantern'                 => '1F3EE',
		'jack_o_lantern'                  => '1F383',
		'japan'                           => '1F5FE',
		'japanese_castle'                 => '1F3EF',
		'japanese_goblin'                 => '1F47A',
		'japanese_ogre'                   => '1F479',
		'jeans'                           => '1F456',
		'joy'                             => '1F602',
		'joy_cat'                         => '1F639',
		'key'                             => '1F511',
		'keycap_ten'                      => '1F51F',
		'kimono'                          => '1F458',
		'kiss'                            => '1F48B',
		'kissing'                         => '1F617',
		'kissing_cat'                     => '1F63D',
		'kissing_closed_eyes'             => '1F61A',
		'kissing_heart'                   => '1F618',
		'kissing_smiling_eyes'            => '1F619',
		'koala'                           => '1F428',
		'koko'                            => '1F201',
		'lantern'                         => '1F3EE',
		'large_blue_circle'               => '1F535',
		'large_blue_diamond'              => '1F537',
		'large_orange_diamond'            => '1F536',
		'last_quarter_moon'               => '1F317',
		'last_quarter_moon_with_face'     => '1F31C',
		'laughing'                        => '1F606',
		'leaves'                          => '1F343',
		'ledger'                          => '1F4D2',
		'left_luggage'                    => '1F6C5',
		'left_right_arrow'                => '2194',
		'leftwards_arrow_with_hook'       => '21A9',
		'lemon'                           => '1F34B',
		'leo'                             => '264C',
		'leopard'                         => '1F406',
		'libra'                           => '264E',
		'light_rail'                      => '1F688',
		'link'                            => '1F517',
		'lips'                            => '1F444',
		'lipstick'                        => '1F484',
		'lock'                            => '1F512',
		'lock_with_ink_pen'               => '1F50F',
		'lollipop'                        => '1F36D',
		'loop'                            => '27BF',
		'loudspeaker'                     => '1F4E2',
		'love_hotel'                      => '1F3E9',
		'love_letter'                     => '1F48C',
		'low_brightness'                  => '1F505',
		'm'                               => '24C2',
		'mag'                             => '1F50D',
		'mag_right'                       => '1F50E',
		'mahjong'                         => '1F004',
		'mailbox'                         => '1F4EB',
		'mailbox_closed'                  => '1F4EA',
		'mailbox_with_mail'               => '1F4EC',
		'mailbox_with_no_mail'            => '1F4ED',
		'man'                             => '1F468',
		'man_with_gua_pi_mao'             => '1F472',
		'man_with_turban'                 => '1F473',
		'mans_shoe'                       => '1F45E',
		'maple_leaf'                      => '1F341',
		'mask'                            => '1F637',
		'massage'                         => '1F486',
		'meat_on_bone'                    => '1F356',
		'mega'                            => '1F4E3',
		'melon'                           => '1F348',
		'memo'                            => '1F4DD',
		'mens'                            => '1F6B9',
		'metro'                           => '1F687',
		'microphone'                      => '1F3A4',
		'microscope'                      => '1F52C',
		'milky_way'                       => '1F30C',
		'minibus'                         => '1F690',
		'minidisc'                        => '1F4BD',
		'mobile_phone_off'                => '1F4F4',
		'money_with_wings'                => '1F4B8',
		'moneybag'                        => '1F4B0',
		'monkey'                          => '1F412',
		'monkey_face'                     => '1F435',
		'monorail'                        => '1F69D',
		'moon'                            => '1F314',
		'mortar_board'                    => '1F393',
		'mount_fuji'                      => '1F5FB',
		'mountain_bicyclist'              => '1F6B5',
		'mountain_cableway'               => '1F6A0',
		'mountain_railway'                => '1F69E',
		'mouse'                           => '1F42D',
		'mouse2'                          => '1F401',
		'movie_camera'                    => '1F3A5',
		'moyai'                           => '1F5FF',
		'muscle'                          => '1F4AA',
		'mushroom'                        => '1F344',
		'musical_keyboard'                => '1F3B9',
		'musical_note'                    => '1F3B5',
		'musical_score'                   => '1F3BC',
		'mute'                            => '1F507',
		'nail_care'                       => '1F485',
		'name_badge'                      => '1F4DB',
		'necktie'                         => '1F454',
		'negative_squared_cross_mark'     => '274E',
		'neutral_face'                    => '1F610',
		'new'                             => '1F195',
		'new_moon'                        => '1F311',
		'new_moon_with_face'              => '1F31A',
		'newspaper'                       => '1F4F0',
		'ng'                              => '1F196',
		'no_bell'                         => '1F515',
		'no_bicycles'                     => '1F6B3',
		'no_entry'                        => '26D4',
		'no_entry_sign'                   => '1F6AB',
		'no_good'                         => '1F645',
		'no_mobile_phones'                => '1F4F5',
		'no_mouth'                        => '1F636',
		'no_pedestrians'                  => '1F6B7',
		'no_smoking'                      => '1F6AD',
		'non-potable_water'               => '1F6B1',
		'nose'                            => '1F443',
		'notebook'                        => '1F4D3',
		'notebook_with_decorative_cover'  => '1F4D4',
		'notes'                           => '1F3B6',
		'nut_and_bolt'                    => '1F529',
		'o'                               => '2B55',
		'o2'                              => '1F17E',
		'ocean'                           => '1F30A',
		'octopus'                         => '1F419',
		'oden'                            => '1F362',
		'office'                          => '1F3E2',
		'ok'                              => '1F197',
		'ok_hand'                         => '1F44C',
		'ok_woman'                        => '1F646',
		'older_man'                       => '1F474',
		'older_woman'                     => '1F475',
		'on'                              => '1F51B',
		'oncoming_automobile'             => '1F698',
		'oncoming_bus'                    => '1F68D',
		'oncoming_police_car'             => '1F694',
		'oncoming_taxi'                   => '1F696',
		'open_book'                       => '1F4D6',
		'open_file_folder'                => '1F4C2',
		'open_hands'                      => '1F450',
		'open_mouth'                      => '1F62E',
		'ophiuchus'                       => '26CE',
		'orange_book'                     => '1F4D9',
		'outbox_tray'                     => '1F4E4',
		'ox'                              => '1F402',
		'package'                         => '1F4E6',
		'page_facing_up'                  => '1F4C4',
		'page_with_curl'                  => '1F4C3',
		'pager'                           => '1F4DF',
		'palm_tree'                       => '1F334',
		'panda_face'                      => '1F43C',
		'paperclip'                       => '1F4CE',
		'parking'                         => '1F17F',
		'part_alternation_mark'           => '303D',
		'partly_sunny'                    => '26C5',
		'passport_control'                => '1F6C2',
		'paw_prints'                      => '1F43E',
		'peach'                           => '1F351',
		'pear'                            => '1F350',
		'pencil'                          => '1F4DD',
		'pencil2'                         => '270F',
		'penguin'                         => '1F427',
		'pensive'                         => '1F614',
		'performing_arts'                 => '1F3AD',
		'persevere'                       => '1F623',
		'person_frowning'                 => '1F64D',
		'person_with_blond_hair'          => '1F471',
		'person_with_pouting_face'        => '1F64E',
		'phone'                           => '260E',
		'pig'                             => '1F437',
		'pig2'                            => '1F416',
		'pig_nose'                        => '1F43D',
		'pill'                            => '1F48A',
		'pineapple'                       => '1F34D',
		'pisces'                          => '2653',
		'pizza'                           => '1F355',
		'point_down'                      => '1F447',
		'point_left'                      => '1F448',
		'point_right'                     => '1F449',
		'point_up'                        => '261D',
		'point_up_2'                      => '1F446',
		'police_car'                      => '1F693',
		'poodle'                          => '1F429',
		'poop'                            => '1F4A9',
		'post_office'                     => '1F3E3',
		'postal_horn'                     => '1F4EF',
		'postbox'                         => '1F4EE',
		'potable_water'                   => '1F6B0',
		'pouch'                           => '1F45D',
		'poultry_leg'                     => '1F357',
		'pound'                           => '1F4B7',
		'pouting_cat'                     => '1F63E',
		'pray'                            => '1F64F',
		'princess'                        => '1F478',
		'punch'                           => '1F44A',
		'purple_heart'                    => '1F49C',
		'purse'                           => '1F45B',
		'pushpin'                         => '1F4CC',
		'put_litter_in_its_place'         => '1F6AE',
		'question'                        => '2753',
		'rabbit'                          => '1F430',
		'rabbit2'                         => '1F407',
		'racehorse'                       => '1F40E',
		'radio'                           => '1F4FB',
		'radio_button'                    => '1F518',
		'rage'                            => '1F621',
		'railway_car'                     => '1F683',
		'rainbow'                         => '1F308',
		'raised_hand'                     => '270B',
		'raised_hands'                    => '1F64C',
		'raising_hand'                    => '1F64B',
		'ram'                             => '1F40F',
		'ramen'                           => '1F35C',
		'rat'                             => '1F400',
		'recycle'                         => '267B',
		'red_car'                         => '1F697',
		'red_circle'                      => '1F534',
		'registered'                      => 'AE',
		'relaxed'                         => '263A',
		'relieved'                        => '1F60C',
		'repeat'                          => '1F501',
		'repeat_one'                      => '1F502',
		'restroom'                        => '1F6BB',
		'revolving_hearts'                => '1F49E',
		'rewind'                          => '23EA',
		'ribbon'                          => '1F380',
		'rice'                            => '1F35A',
		'rice_ball'                       => '1F359',
		'rice_cracker'                    => '1F358',
		'rice_scene'                      => '1F391',
		'ring'                            => '1F48D',
		'rocket'                          => '1F680',
		'roller_coaster'                  => '1F3A2',
		'rooster'                         => '1F413',
		'rose'                            => '1F339',
		'rotating_light'                  => '1F6A8',
		'round_pushpin'                   => '1F4CD',
		'rowboat'                         => '1F6A3',
		'rugby_football'                  => '1F3C9',
		'runner'                          => '1F3C3',
		'running'                         => '1F3C3',
		'running_shirt_with_sash'         => '1F3BD',
		'sa'                              => '1F202',
		'sagittarius'                     => '2650',
		'sailboat'                        => '26F5',
		'sake'                            => '1F376',
		'sandal'                          => '1F461',
		'santa'                           => '1F385',
		'satellite'                       => '1F4E1',
		'satisfied'                       => '1F606',
		'saxophone'                       => '1F3B7',
		'school'                          => '1F3EB',
		'school_satchel'                  => '1F392',
		'scissors'                        => '2702',
		'scorpius'                        => '264F',
		'scream'                          => '1F631',
		'scream_cat'                      => '1F640',
		'scroll'                          => '1F4DC',
		'seat'                            => '1F4BA',
		'secret'                          => '3299',
		'see_no_evil'                     => '1F648',
		'seedling'                        => '1F331',
		'shaved_ice'                      => '1F367',
		'sheep'                           => '1F411',
		'shell'                           => '1F41A',
		'ship'                            => '1F6A2',
		'shirt'                           => '1F455',
		'shit'                            => '1F4A9',
		'shoe'                            => '1F45E',
		'shower'                          => '1F6BF',
		'signal_strength'                 => '1F4F6',
		'six_pointed_star'                => '1F52F',
		'ski'                             => '1F3BF',
		'skull'                           => '1F480',
		'sleeping'                        => '1F634',
		'sleepy'                          => '1F62A',
		'slot_machine'                    => '1F3B0',
		'small_blue_diamond'              => '1F539',
		'small_orange_diamond'            => '1F538',
		'small_red_triangle'              => '1F53A',
		'small_red_triangle_down'         => '1F53B',
		'smile'                           => '1F604',
		'smile_cat'                       => '1F638',
		'smiley'                          => '1F603',
		'smiley_cat'                      => '1F63A',
		'smiling_imp'                     => '1F608',
		'smirk'                           => '1F60F',
		'smirk_cat'                       => '1F63C',
		'smoking'                         => '1F6AC',
		'snail'                           => '1F40C',
		'snake'                           => '1F40D',
		'snowboarder'                     => '1F3C2',
		'snowflake'                       => '2744',
		'snowman'                         => '26C4',
		'sob'                             => '1F62D',
		'soccer'                          => '26BD',
		'soon'                            => '1F51C',
		'sos'                             => '1F198',
		'sound'                           => '1F509',
		'space_invader'                   => '1F47E',
		'spades'                          => '2660',
		'spaghetti'                       => '1F35D',
		'sparkle'                         => '2747',
		'sparkler'                        => '1F387',
		'sparkles'                        => '2728',
		'sparkling_heart'                 => '1F496',
		'speak_no_evil'                   => '1F64A',
		'speaker'                         => '1F50A',
		'speech_balloon'                  => '1F4AC',
		'speedboat'                       => '1F6A4',
		'star'                            => '2B50',
		'star2'                           => '1F31F',
		'stars'                           => '1F303',
		'station'                         => '1F689',
		'statue_of_liberty'               => '1F5FD',
		'steam_locomotive'                => '1F682',
		'stew'                            => '1F372',
		'straight_ruler'                  => '1F4CF',
		'strawberry'                      => '1F353',
		'stuck_out_tongue'                => '1F61B',
		'stuck_out_tongue_closed_eyes'    => '1F61D',
		'stuck_out_tongue_winking_eye'    => '1F61C',
		'sun_with_face'                   => '1F31E',
		'sunflower'                       => '1F33B',
		'sunglasses'                      => '1F60E',
		'sunny'                           => '2600',
		'sunrise'                         => '1F305',
		'sunrise_over_mountains'          => '1F304',
		'surfer'                          => '1F3C4',
		'sushi'                           => '1F363',
		'suspension_railway'              => '1F69F',
		'sweat'                           => '1F613',
		'sweat_drops'                     => '1F4A6',
		'sweat_smile'                     => '1F605',
		'sweet_potato'                    => '1F360',
		'swimmer'                         => '1F3CA',
		'symbols'                         => '1F523',
		'syringe'                         => '1F489',
		'tada'                            => '1F389',
		'tanabata_tree'                   => '1F38B',
		'tangerine'                       => '1F34A',
		'taurus'                          => '2649',
		'taxi'                            => '1F695',
		'tea'                             => '1F375',
		'telephone'                       => '260E',
		'telephone_receiver'              => '1F4DE',
		'telescope'                       => '1F52D',
		'tennis'                          => '1F3BE',
		'tent'                            => '26FA',
		'thought_balloon'                 => '1F4AD',
		'thumbsdown'                      => '1F44E',
		'thumbsup'                        => '1F44D',
		'ticket'                          => '1F3AB',
		'tiger'                           => '1F42F',
		'tiger2'                          => '1F405',
		'tired_face'                      => '1F62B',
		'tm'                              => '2122',
		'toilet'                          => '1F6BD',
		'tokyo_tower'                     => '1F5FC',
		'tomato'                          => '1F345',
		'tongue'                          => '1F445',
		'top'                             => '1F51D',
		'tophat'                          => '1F3A9',
		'tractor'                         => '1F69C',
		'traffic_light'                   => '1F6A5',
		'train'                           => '1F683',
		'train2'                          => '1F686',
		'tram'                            => '1F68A',
		'triangular_flag_on_post'         => '1F6A9',
		'triangular_ruler'                => '1F4D0',
		'trident'                         => '1F531',
		'triumph'                         => '1F624',
		'trolleybus'                      => '1F68E',
		'trophy'                          => '1F3C6',
		'tropical_drink'                  => '1F379',
		'tropical_fish'                   => '1F420',
		'truck'                           => '1F69A',
		'trumpet'                         => '1F3BA',
		'tshirt'                          => '1F455',
		'tulip'                           => '1F337',
		'turtle'                          => '1F422',
		'tv'                              => '1F4FA',
		'twisted_rightwards_arrows'       => '1F500',
		'two_hearts'                      => '1F495',
		'two_men_holding_hands'           => '1F46C',
		'two_women_holding_hands'         => '1F46D',
		'u5272'                           => '1F239',
		'u5408'                           => '1F234',
		'u55b6'                           => '1F23A',
		'u6307'                           => '1F22F',
		'u6708'                           => '1F237',
		'u6709'                           => '1F236',
		'u6e80'                           => '1F235',
		'u7121'                           => '1F21A',
		'u7533'                           => '1F238',
		'u7981'                           => '1F232',
		'u7a7a'                           => '1F233',
		'umbrella'                        => '2614',
		'unamused'                        => '1F612',
		'underage'                        => '1F51E',
		'unlock'                          => '1F513',
		'up'                              => '1F199',
		'v'                               => '270C',
		'vertical_traffic_light'          => '1F6A6',
		'vhs'                             => '1F4FC',
		'vibration_mode'                  => '1F4F3',
		'video_camera'                    => '1F4F9',
		'video_game'                      => '1F3AE',
		'violin'                          => '1F3BB',
		'virgo'                           => '264D',
		'volcano'                         => '1F30B',
		'vs'                              => '1F19A',
		'walking'                         => '1F6B6',
		'waning_crescent_moon'            => '1F318',
		'waning_gibbous_moon'             => '1F316',
		'warning'                         => '26A0',
		'watch'                           => '231A',
		'water_buffalo'                   => '1F403',
		'watermelon'                      => '1F349',
		'wave'                            => '1F44B',
		'wavy_dash'                       => '3030',
		'waxing_crescent_moon'            => '1F312',
		'waxing_gibbous_moon'             => '1F314',
		'wc'                              => '1F6BE',
		'weary'                           => '1F629',
		'wedding'                         => '1F492',
		'whale'                           => '1F433',
		'whale2'                          => '1F40B',
		'wheelchair'                      => '267F',
		'white_check_mark'                => '2705',
		'white_circle'                    => '26AA',
		'white_flower'                    => '1F4AE',
		'white_large_square'              => '2B1C',
		'white_medium_small_square'       => '25FD',
		'white_medium_square'             => '25FB',
		'white_small_square'              => '25AB',
		'white_square_button'             => '1F533',
		'wind_chime'                      => '1F390',
		'wine_glass'                      => '1F377',
		'wink'                            => '1F609',
		'wolf'                            => '1F43A',
		'woman'                           => '1F469',
		'womans_clothes'                  => '1F45A',
		'womans_hat'                      => '1F452',
		'womens'                          => '1F6BA',
		'worried'                         => '1F61F',
		'wrench'                          => '1F527',
		'x'                               => '274C',
		'yellow_heart'                    => '1F49B',
		'yen'                             => '1F4B4',
		'yum'                             => '1F60B',
		'zap'                             => '26A1',
		'zzz'                             => '1F4A4',
	);

	/**
	 * The character prefixes.
	 *
	 * @var array
	 */
	protected $prefixes = array( ':', '\x{003a}' );

	/**
	 * The emoji cdn.
	 *
	 * @var string
	 */
	protected $emoji_cdn = '//s.w.org/images/core/emoji/72x72/';

	/**
	 * Called by WPCOM_Liveblog_Entry_Extend::load().
	 *
	 * @return void
	 */
	public function load() {

		// Store the path to the liveblog plugin.
		$this->path = dirname( __FILE__ );

		// Allow plugins, themes, etc. to change
		// the generated emoji class.
		$this->class_prefix = apply_filters( 'liveblog_emoji_class', $this->class_prefix );

		// Allow plugins, themes, etc. to change
		// the active emojis.
		$this->emojis = apply_filters( 'liveblog_active_emojis', $this->emojis );

		// Allow plugins, themes, etc. to change
		// the emoji cdn url.
		$this->emoji_cdn = apply_filters( 'liveblog_cdn_emojis', $this->emoji_cdn );

		// This is the regex used to revert the
		// generated emoji html back to the
		// raw input format (e.g :poop:).
		$this->revert_regex = implode(
			'',
			array(
				preg_quote( '<img src="', '~' ),
				preg_quote( $this->emoji_cdn, '~' ),
				'[^"]+',
				preg_quote( '" class="liveblog-emoji ', '~' ),
				preg_quote( $this->class_prefix, '~' ),
				'([^"]+)',
				preg_quote( '">', '~' ),
			)
		);

		// Allow plugins, themes, etc. to change the revert regex.
		$this->revert_regex = apply_filters( 'liveblog_emoji_revert_regex', $this->revert_regex );

		// We hook into the comment_class filter to
		// be able to alter the comment content.
		add_filter( 'comment_class', array( $this, 'add_emoji_class_to_entry' ), 10, 3 );
	}

	/**
	 * Gets the autocomplete config.
	 *
	 * @param array $config
	 *
	 * @return array
	 */
	public function get_config( $config ) {
		$emojis = array();

		// Map the emojis into the format the front end expects it.
		foreach ( $this->get_emojis() as $key => $val ) {
			$emojis[] = $this->map_emoji( $val, $key );
		}

		// Add our config to the front end autocomplete
		// config, after first allowing other plugins,
		// themes, etc. to modify it as required
		$config[] = apply_filters(
			'liveblog_emoji_config',
			array(
				'type'        => 'static',
				'data'        => $emojis,
				'search'      => 'key',
				'replacement' => ':${key}:',
				'displayKey'  => 'key',
				'replaceText' => ':$:',
				'trigger'     => ':',
				'name'        => 'Emoji',
				'cdn'         => esc_url( $this->emoji_cdn ),
				'template'    => '<img src="' . esc_url( $this->emoji_cdn ) . '${image}.png" height="20" width="20" /> ${name}',
			)
		);

		return $config;
	}

	/**
	 * Maps an emoji for at.js.
	 *
	 * @param string $val
	 * @param string $key
	 *
	 * @return array
	 */
	public function map_emoji( $val, $key ) {
		// Map the emojis into the format of:
		// [ :key, :name, :image ]
		//
		// Then pass it into a filter to allow plugins,
		// themes, etc. to customise the output.
		return apply_filters(
			'liveblog_emoji_map',
			array(
				'key'   => $key,
				'name'  => $key,
				'image' => strtolower( $val ),
			)
		);
	}

	/**
	 * Get all the available emojis.
	 *
	 * @return array
	 */
	public function get_emojis() {
		return $this->emojis;
	}

	/**
	 * Sets the regex.
	 *
	 * @param string $regex
	 *
	 * @return void
	 */
	public function set_regex( $regex ) {
		// We alter the regex here to allow for a slightly
		// extended set of characters at the start.
		$regex_prefix  = substr( $regex, 0, strlen( $regex ) - 10 );
		$regex_postfix = substr( $regex, strlen( $regex ) - 10 );
		$this->regex   = $regex_prefix . '(?:' . implode( '|', $this->get_prefixes() ) . ')' . $regex_postfix;
		$this->regex   = str_replace( '\p{L}', '\p{L}\\+\\-0-9', $this->regex );
	}

	/**
	 * Filters the input.
	 *
	 * @param mixed $entry
	 *
	 * @return mixed
	 */
	public function filter( $entry ) {

		// Map over every match and apply it via the
		// preg_replace_callback method.
		$entry['content'] = preg_replace_callback(
			$this->get_regex(),
			array( $this, 'preg_replace_callback' ),
			$entry['content']
		);

		return $entry;
	}

	/**
	 * The preg replace callback for the filter.
	 *
	 * @param array $match
	 *
	 * @return string
	 */
	public function preg_replace_callback( $match ) {
		// If the emoji doesn't exist then don't
		// continue to match it and render it.
		if ( ! isset( $this->emojis[ $match[2] ] ) ) {
			return $match[0];
		}

		$emoji = $match[2];

		// Grab the image key from the set emojis.
		$image = $this->map_emoji( $this->emojis[ $emoji ], $emoji );
		$image = $image['image'];

		// Replace the emoji with a img tag of the emoji.
		return str_replace(
			$match[1],
			'<img src="' . $this->emoji_cdn . $image . '.png" class="liveblog-emoji ' . $this->class_prefix . $emoji . '" data-emoji="' . $emoji . '">',
			$match[0]
		);
	}

	/**
	 * Reverts the input.
	 *
	 * @param mixed $content
	 *
	 * @return mixed
	 */
	public function revert( $content ) {
		return preg_replace( '~' . $this->revert_regex . '~', ':$1:', $content );
	}

	/**
	 * Adds emoji-{emoji} class to entry.
	 *
	 * @param array  $classes
	 * @param string $class
	 * @param int    $comment_id
	 *
	 * @return array
	 */
	public function add_emoji_class_to_entry( $classes, $class, $comment_id ) {
		$emojis  = array();
		$comment = get_comment( $comment_id );

		// Check if the comment is a live blog comment.
		if ( WPCOM_Liveblog::KEY === $comment->comment_type ) {

			// Grab all the prefixed classes applied.
			preg_match_all( '/(?<!\w)' . preg_quote( $this->class_prefix, '/' ) . '\w+/', $comment->comment_content, $emojis );

			// Append the first class to the classes array.
			$classes = array_merge( $classes, $emojis[0] );
		}

		return $classes;
	}

}
