<?php

declare(strict_types=1);

namespace OCA\Nostalgist\Tests\Unit;

use OCA\Nostalgist\Service\SettingsService;
use OCP\IAppConfig;
use OCP\IConfig;
use PHPUnit\Framework\TestCase;

class SettingsServiceTest extends TestCase {
	private const USER = 'alice';

	private function service(string $stored = ''): SettingsService {
		$config = $this->createStub(IConfig::class);
		$config->method('getUserValue')->willReturn($stored);
		return new SettingsService($config, $this->createStub(IAppConfig::class));
	}

	/**
	 * @return array<string, mixed> the settings as they are stored
	 */
	private function save(array $settings): array {
		$config = $this->createMock(IConfig::class);
		$saved = '';
		$config->method('setUserValue')->willReturnCallback(
			function (string $user, string $app, string $key, string $value) use (&$saved): void {
				$saved = $value;
			},
		);
		$config->method('getUserValue')->willReturnCallback(static fn (): string => $saved);
		(new SettingsService($config, $this->createStub(IAppConfig::class)))->setUserSettings(self::USER, $settings);
		return json_decode($saved, true) ?? [];
	}

	/**
	 * @return array<string, array<string, string>> the core options as stored
	 */
	private function saveInstance(array $settings): array {
		$appConfig = $this->createMock(IAppConfig::class);
		$saved = '';
		$appConfig->method('setValueString')->willReturnCallback(
			function (string $app, string $key, string $value) use (&$saved): bool {
				if ($key === 'core_options') {
					$saved = $value;
				}
				return true;
			},
		);
		$appConfig->method('getValueString')->willReturnCallback(
			fn (string $app, string $key): string => $key === 'core_options' ? $saved : '',
		);
		(new SettingsService($this->createStub(IConfig::class), $appConfig))->setInstanceDefaults($settings);
		return json_decode($saved, true) ?? [];
	}

	public function testDefaultsAreReturnedWithoutStoredSettings(): void {
		$settings = $this->service()->getUserSettings(self::USER);
		$this->assertFalse($settings['video_smooth']);
		$this->assertSame(3, $settings['fastforward_ratio']);
		$this->assertSame(0, $settings['audio_volume']);
		$this->assertSame(64, $settings['audio_latency']);
		$this->assertTrue($settings['pause_when_hidden']);
		$this->assertTrue($settings['autosave_on_close']);
		$this->assertFalse($settings['autoload_on_start'], 'a game does not resume by itself unless asked');
		$this->assertSame(0, $settings['autosave_interval'], 'and does not save by itself either');
		$this->assertTrue($settings['respond_to_global_events']);
		$this->assertSame('/Games', $settings['library_folder']);
		$this->assertSame('', $settings['saves_folder']);
		$this->assertSame([], $settings['core_options']);
	}

	public function testBrokenStoredSettingsFallBackToTheDefaults(): void {
		$this->assertSame(
			$this->service()->getDefaults(),
			$this->service('not json at all')->getUserSettings(self::USER),
		);
	}

	public function testStoredSettingsOverrideTheDefaults(): void {
		$stored = json_encode(['video_smooth' => true, 'library_folder' => '/Roms']);
		$settings = $this->service($stored)->getUserSettings(self::USER);
		$this->assertTrue($settings['video_smooth']);
		$this->assertSame('/Roms', $settings['library_folder']);
		// Untouched settings keep their default.
		$this->assertSame(3, $settings['fastforward_ratio']);
	}

	public function testFoldersAreNormalized(): void {
		$saved = $this->save([
			'library_folder' => 'Games/Roms/',
			'saves_folder' => '  /Saves  ',
			'screenshots_folder' => '',
		]);
		$this->assertSame('/Games/Roms', $saved['library_folder']);
		$this->assertSame('/Saves', $saved['saves_folder']);
		$this->assertSame('', $saved['screenshots_folder'], 'an empty folder disables the feature');
	}

	public function testFoldersCannotEscapeTheUserFolder(): void {
		$saved = $this->save(['library_folder' => '../../etc', 'thumbnails_folder' => 'a/../../b']);
		$this->assertArrayNotHasKey('library_folder', $saved);
		$this->assertArrayNotHasKey('thumbnails_folder', $saved);
	}

	public function testTheLibraryFolderCannotBeEmptied(): void {
		$saved = $this->save(['library_folder' => '']);
		$this->assertArrayNotHasKey('library_folder', $saved);
	}

	public function testFastForwardRatioIsClamped(): void {
		// Stored as JSON, where a round number comes back as an integer.
		$this->assertEquals(5, $this->save(['fastforward_ratio' => 1000])['fastforward_ratio']);
		$this->assertEquals(1, $this->save(['fastforward_ratio' => -5])['fastforward_ratio']);
		$this->assertEquals(2.5, $this->save(['fastforward_ratio' => '2.5'])['fastforward_ratio']);
		$this->assertArrayNotHasKey('fastforward_ratio', $this->save(['fastforward_ratio' => 'fast']));
	}

	public function testAudioSettingsAreClamped(): void {
		$this->assertEquals(10, $this->save(['audio_volume' => 100])['audio_volume']);
		$this->assertEquals(-20, $this->save(['audio_volume' => -100])['audio_volume']);
		$this->assertEquals(-6, $this->save(['audio_volume' => '-6'])['audio_volume']);
		$this->assertArrayNotHasKey('audio_volume', $this->save(['audio_volume' => 'loud']));

		$this->assertSame(256, $this->save(['audio_latency' => 5000])['audio_latency']);
		$this->assertSame(16, $this->save(['audio_latency' => 0])['audio_latency']);
		$this->assertSame(96, $this->save(['audio_latency' => '96'])['audio_latency']);
	}

	public function testPlayerTogglesAreStoredAsBooleans(): void {
		$saved = $this->save([
			'scale_integer' => 'true',
			'pause_when_hidden' => '0',
			'autosave_on_close' => false,
		]);
		$this->assertTrue($saved['scale_integer']);
		$this->assertFalse($saved['pause_when_hidden']);
		$this->assertFalse($saved['autosave_on_close']);
	}

	public function testTheAutosaveIntervalTakesOnlyTheOfferedValues(): void {
		$this->assertSame(0, $this->save(['autosave_interval' => 0])['autosave_interval']);
		$this->assertSame(30, $this->save(['autosave_interval' => '30'])['autosave_interval']);
		$this->assertSame(600, $this->save(['autosave_interval' => 600])['autosave_interval']);
		// Anything else would be a value the settings never offered.
		$this->assertArrayNotHasKey('autosave_interval', $this->save(['autosave_interval' => 45]));
		$this->assertArrayNotHasKey('autosave_interval', $this->save(['autosave_interval' => -60]));
		$this->assertArrayNotHasKey('autosave_interval', $this->save(['autosave_interval' => 'often']));
	}

	public function testResumingByItselfIsStoredAsABoolean(): void {
		$this->assertTrue($this->save(['autoload_on_start' => 'true'])['autoload_on_start']);
		$this->assertFalse($this->save(['autoload_on_start' => '0'])['autoload_on_start']);
	}

	public function testTheKeyboardIsBoundToTheControllerOutOfTheBox(): void {
		$settings = $this->service()->getUserSettings(self::USER);
		$this->assertSame('ArrowUp', $settings['buttons']['up']);
		$this->assertSame('KeyX', $settings['buttons']['a']);
		$this->assertSame('Space', $settings['hotkeys']['pause']);
	}

	public function testRebindingAKeyLeavesTheOthersWhereTheyWere(): void {
		$saved = $this->save(['buttons' => ['a' => 'KeyM', 'made_up' => 'KeyN']]);
		$this->assertSame('KeyM', $saved['buttons']['a'], 'the key that was set');
		$this->assertSame('KeyZ', $saved['buttons']['b'], 'and the rest as they were');
		$this->assertArrayNotHasKey('made_up', $saved['buttons']);
	}

	public function testCoreOptionsAreNotAUsersToSet(): void {
		// They hold for everybody playing a core, so they live with the
		// administration settings.
		$saved = $this->save(['core_options' => ['fceumm' => ['fceumm_palette' => 'wavebeam']]]);
		$this->assertArrayNotHasKey('core_options', $saved);
	}

	public function testUnknownSettingsAreDropped(): void {
		$this->assertArrayNotHasKey('evil', $this->save(['evil' => 'value']));
	}

	public function testCoreOptionsAreCheckedAgainstWhatTheCoresOffer(): void {
		$saved = $this->saveInstance([
			'core_options' => [
				'fceumm' => [
					'fceumm_palette' => 'wavebeam',
					'fceumm_palette_made_up' => 'value',
					'fceumm_region' => 'Klingon',
				],
				'made_up_core' => ['option' => 'value'],
			],
		]);
		$this->assertSame(['fceumm' => ['fceumm_palette' => 'wavebeam']], $saved);
	}
}
