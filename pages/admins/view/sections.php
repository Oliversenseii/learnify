<!-- Main Container for Left and Right Sections -->
<div class="main-container">
                <!-- Register Form Section (Left) -->
                <div class="register-section">
                    <h2>Register New Section</h2>
                    <form action="section.php" method="POST">
                        <label for="sectionCode">Section Code</label>
                        <input type="text" name="sectionCode" id="sectionCode" required>

                        <label for="sectionName">Section Name</label>
                        <input type="text" name="sectionName" id="sectionName" required>

                        <label for="strandID">Strand</label>
                        <select name="strandID" id="strandID" required>
                            <option value="" disabled selected>- Select Strand -</option>
                            <!-- Populate this dynamically with the available strands -->
                            <?php
                            $sql = "SELECT strandID, strandName FROM track_strands WHERE archived = 0";
                            $stmt = $dbConnection->prepare($sql);
                            $stmt->execute();
                            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                echo "<option value='" . $row['strandID'] . "'>" . htmlspecialchars($row['strandName']) . "</option>";
                            }
                            ?>
                        </select>

                        <label for="gradeLevel">Grade Level</label>
                        <select name="gradeLevel" id="gradeLevel" required>
                            <option value="" disabled selected>- Select Grade -</option>
                            <option value="Grade 11">Grade 11</option>
                            <option value="Grade 12">Grade 12</option>
                        </select>

                        <label for="semester">Semester</label>
                        <select name="semester" id="semester" required>
                            <option value="" disabled selected>- Select Semester -</option>
                            <option value="1st Sem">1st Sem</option>
                            <option value="2nd Sem">2nd Sem</option>
                        </select>

                        <button type="submit" name="registerSection">Register Section</button>
                    </form>
                </div>

                <!-- View All Sections Section (Right) -->
                <div class="view-all-section">
                    <h2>View All Sections</h2>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Section Code</th>
                                    <th>Section Name</th>
                                    <th>Strand</th>
                                    <th>Grade Level</th>
                                    <th>Semester</th>
                                    <th>Date Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $sql = "SELECT s.sectionID, s.sectionCode, s.sectionName, ts.strandName, s.gradeLevel, s.semester, s.dateCreated 
                                        FROM sections s
                                        JOIN track_strands ts ON s.strandID = ts.strandID
                                        WHERE s.archived = 0";
                                $stmt = $dbConnection->prepare($sql);
                                $stmt->execute();

                                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                    echo "<tr>";
                                    echo "<td>" . htmlspecialchars($row['sectionCode']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['sectionName']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['strandName']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['gradeLevel']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['semester']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['dateCreated']) . "</td>";
                                    echo "<td>
                                            <form action='edit_section.php' method='GET' style='display: inline;'>
                                                <button type='submit' name='id' value='" . $row['sectionID'] . "' class='btn edit-btn'>Edit</button>
                                            </form>
                                            <button type='button' onclick='confirmArchive(" . $row['sectionID'] . ")' class='btn archive-btn'>Archive</button>
                                        </td>";
                                    echo "</tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>