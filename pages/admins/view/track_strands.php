
			<!-- Main Container for Left and Right Sections -->
			<div class="main-container">
				<!-- Register Form Section (Left) -->
				<div class="register-section">
					<h2>Register New Track & Strand</h2>
					<form action="track-strands.php" method="POST">
						<label for="strandCode">Strand Code</label>
						<input type="text" name="strandCode" id="strandCode" required>

						<label for="strandName">Strand Name</label>
						<input type="text" name="strandName" id="strandName" required>

						<label for="trackName">Track Name</label>
						<input type="text" name="trackName" id="trackName" required>

						<label for="description">Description</label>
						<textarea name="description" id="description" required></textarea>

						<button type="submit" name="registerStrand">Register Strand</button>
					</form>
				</div>

				<!-- View All Track & Strands Section (Right) -->
				<div class="view-all-section">
					<h2>View All Strands</h2>
					<div class="table-wrapper"> 
						<table>
							<thead>
								<tr>
									<th>Strand Code</th>
									<th>Strand Name</th>
									<th>Track Name</th>
									<th>Date Created</th>
									<th>Actions</th>
								</tr>
							</thead>
							<tbody>
								<?php
								$sql = "SELECT * FROM track_strands WHERE archived = 0";
								$stmt = $dbConnection->prepare($sql);
								$stmt->execute();

								while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
									echo "<tr>";
									echo "<td>" . htmlspecialchars($row['strandCode']) . "</td>";
									echo "<td>" . htmlspecialchars($row['strandName']) . "</td>";
									echo "<td>" . htmlspecialchars($row['trackName']) . "</td>";
									echo "<td>" . htmlspecialchars($row['dateCreated']) . "</td>";
									echo "<td>
											<form action='edit_track_strands.php' method='GET' style='display: inline;'>
												<button type='submit' name='id' value='" . $row['strandID'] . "' class='btn edit-btn'>Edit</button>
											</form>
											<form action='#' method='POST' style='display: inline;' id='archiveForm" . $row['strandID'] . "'>
												<button type='button' onclick='confirmArchive(" . $row['strandID'] . ")' class='btn archive-btn'>Archive</button>
											</form>
											</td>";
									echo "</tr>";
								}
								?>
							</tbody>
						</table>
					</div>
				</div>

			</div>